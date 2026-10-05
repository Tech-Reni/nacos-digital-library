<?php

/**
 * upload/upload.php
 * Phase 8/17: File Upload Security & Scanned Document OCR Integration - Google UI Refactor
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Only course reps and above can upload
check_role(['course_rep', 'governor', 'admin', 'student']);

$errors = [];
$success = "";
$level_options = ['ND1', 'ND2', 'ND3', 'HND1', 'HND2', 'HND3'];
$default_department = 'computer-science';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify();

    $title = trim((string) ($_POST['title'] ?? ''));
    $author = trim((string) ($_POST['author'] ?? ''));
    $level = trim((string) ($_POST['level'] ?? ''));
    $department = $default_department;
    $upload_type = trim((string) ($_POST['upload_type'] ?? 'pdf'));

    if (empty($title)) {
        $errors[] = "Title is required.";
    }

    if (empty($author)) {
        $errors[] = "Author is required.";
    }

    if (!in_array($level, $level_options, true)) {
        $errors[] = "Please select a valid class level for this book.";
    }

    if (empty($errors)) {
        $upload_dir = __DIR__ . '/../uploads/protected_books/';
        $cover_dir = $upload_dir . 'covers/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!is_dir($cover_dir)) {
            mkdir($cover_dir, 0755, true);
        }

        $new_filename = bin2hex(random_bytes(16)) . '.pdf';
        $target_path = $upload_dir . $new_filename;
        $file_mime = 'application/pdf';
        $original_filename = '';
        $file_size = 0;
        $cover_db_path = '';

        // Optional custom cover image upload
        $cover_image = $_FILES['cover_image'] ?? null;
        if ($cover_image && !empty($cover_image['name']) && $cover_image['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($cover_image['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Cover image upload failed. Please try again.";
            } else {
                $cover_ext = strtolower(pathinfo($cover_image['name'], PATHINFO_EXTENSION));
                $allowed_cover_exts = ['jpg', 'jpeg', 'png', 'webp'];
                $allowed_cover_mimes = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $cover_mime = $finfo->file($cover_image['tmp_name']);

                if (!in_array($cover_ext, $allowed_cover_exts, true) || !in_array($cover_mime, $allowed_cover_mimes, true)) {
                    $errors[] = "Invalid cover image format. Use JPG, PNG, or WEBP.";
                }

                if (!empty($errors)) {
                    $cover_db_path = '';
                } else {
                    $cover_filename = 'cover_' . bin2hex(random_bytes(8)) . '.' . $cover_ext;
                    $cover_target = $cover_dir . $cover_filename;
                    if (!move_uploaded_file($cover_image['tmp_name'], $cover_target)) {
                        $errors[] = "Failed to save the uploaded cover image.";
                    } else {
                        $cover_db_path = 'uploads/protected_books/covers/' . $cover_filename;
                    }
                }
            }
        }

        if ($upload_type === 'pdf') {
            // === Standard PDF Upload Workflow ===
            $file = $_FILES['book_file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "File upload error. Please select a valid PDF file.";
            } else {
                $allowed_extensions = ['pdf'];
                $allowed_mimes = ['application/pdf'];
                $max_size = 10 * 1024 * 1024;

                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $file_mime = $finfo->file($file['tmp_name']);
                $original_filename = $file['name'];
                $file_size = (int) $file['size'];

                if (!in_array($file_ext, $allowed_extensions, true) || !in_array($file_mime, $allowed_mimes, true)) {
                    $errors[] = "Invalid file type. Only real PDFs are accepted.";
                }
                if ($file_size > $max_size) {
                    $errors[] = "File size exceeds the 10MB limit.";
                }

                if (empty($errors)) {
                    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                        $errors[] = "Failed to save the uploaded PDF.";
                    }
                }
            }
        } else {
            // === Scanned Images -> OCR -> Compilation Pipeline ===
            $images = $_FILES['scanned_images'] ?? null;
            if (!$images || empty($images['name'][0])) {
                $errors[] = "Please select at least one image file for OCR scanning.";
            } else {
                require __DIR__ . '/process_images.php';

                if (empty($errors) && isset($_SESSION['pending_scans'])) {
                    define('OCR_PROCESS_INCLUDED', true);
                    require __DIR__ . '/ocr_process.php';
                    require __DIR__ . '/compile_pdf.php';
                }
            }
        }

        // === Save Final DB Records ===
        if (empty($errors)) {
            $book_uuid = bin2hex(random_bytes(16));
            $uploader_id = (int) $_SESSION['user_id'];
            $final_original_name = $original_filename ?: 'Compiled_OCR_Document.pdf';
            $user_level = $level ?: ($_SESSION['level'] ?? 'ND1');

            if ($upload_type === 'scanned') {
                $file_size = $_SESSION['compiled_file_size'] ?? $file_size;
                $page_count = $_SESSION['compiled_page_count'] ?? 0;
            } else {
                $page_count = 0;
            }

            $book_stmt = $conn->prepare("INSERT INTO books (uuid, title, author, uploader_id, department, status, original_filename, thumbnail_path, level) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
            if (!$book_stmt) {
                $errors[] = "Database schema mismatch. Please run the migration SQL before uploading books.";
            } else {
                $book_stmt->bind_param("sssissss", $book_uuid, $title, $author, $uploader_id, $department, $final_original_name, $cover_db_path, $user_level);
                if ($book_stmt->execute()) {
                    $book_id = $conn->insert_id;

                    $file_stmt = $conn->prepare("INSERT INTO book_files (book_id, storage_key, file_name, mime, size_bytes, pages) VALUES (?, ?, ?, ?, ?, ?)");
                    $file_stmt->bind_param("isssii", $book_id, $new_filename, $final_original_name, $file_mime, $file_size, $page_count);
                    $file_stmt->execute();

                    if ($upload_type === 'scanned' && isset($_SESSION['ocr_results'])) {
                        $ocr_text = json_encode($_SESSION['ocr_results']);
                        $ocr_stmt = $conn->prepare("UPDATE book_files SET ocr_text = ? WHERE book_id = ?");
                        $ocr_stmt->bind_param("si", $ocr_text, $book_id);
                        $ocr_stmt->execute();
                        $ocr_stmt->close();
                    }

                    log_audit($conn, "book_uploaded", "books", $book_id);

                    if (isset($_SESSION['upload_session_uuid'])) {
                        $update_session = $conn->prepare("UPDATE upload_sessions SET status = 'completed' WHERE uuid = ? AND uploader_id = ?");
                        $update_session->bind_param("si", $_SESSION['upload_session_uuid'], $uploader_id);
                        $update_session->execute();
                        $update_session->close();
                        unset($_SESSION['upload_session_uuid']);
                    }

                    if (empty($cover_db_path)) {
                        $first_image_path = null;
                        if ($upload_type === 'scanned' && isset($_SESSION['pending_scans']) && !empty($_SESSION['pending_scans'])) {
                            $first_image_path = $_SESSION['pending_scans'][0]['stored_path'] ?? null;
                        }
                        generate_book_thumbnail($conn, $book_id, $new_filename, $upload_type, $first_image_path);
                    }

                    $_SESSION['last_uploaded_book'] = [
                        'title' => $title,
                        'author' => $author,
                        'department' => $department,
                        'level' => $user_level,
                        'uuid' => $book_uuid,
                        'is_ocr' => ($upload_type === 'scanned')
                    ];

                    unset($_SESSION['pending_scans'], $_SESSION['ocr_results'], $_SESSION['compiled_file_size'], $_SESSION['compiled_page_count']);

                    header("Location: upload_success.php");
                    exit;
                } else {
                    $errors[] = "Database insertion failed: " . $book_stmt->error;
                    cleanup_uploaded_files($upload_type, $target_path);
                }
            }
        }
    }
}

$page_title = "Upload Book";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php render_meta([
        'title'       => 'Upload Academic Resource | NACOS App',
        'description' => 'Upload and share academic books, PDFs and scanned documents in NACOS App to help other Computer Science students.',
    ]); ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #0b8f3a;
            --dark-green: #066b2a;
            --primary-yellow: #ffcc00;
            --text-dark: #111827;
            --text-secondary: #52606d;
            --bg: #f3f7fa;
            --surface: #ffffff;
            --border-subtle: 1px solid rgba(15, 23, 42, 0.08);

            --font-display: 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-metrics: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg);
            font-family: var(--font-body);
            color: var(--text-dark);
        }

        .upload-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 28px;
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 24px 60px;
        }

        .upload-main {
            background: var(--surface);
            border-radius: 16px;
            padding: 32px;
            border: var(--border-subtle);
            box-shadow: 0 4px 24px -4px rgba(15, 23, 42, 0.04);
        }

        .upload-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 24px;
            border: var(--border-subtle);
        }

        .sidebar-card h3 {
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0 0 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-family: var(--font-metrics);
            font-size: 0.88rem;
        }

        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            font-size: 0.9rem;
            outline: none;
            box-sizing: border-box;
            transition: all 0.2s ease;
            font-family: var(--font-body);
        }

        .form-group input[type="text"]:focus,
        .form-group select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(11, 143, 58, 0.1);
        }

        /* Drag-and-Drop Upload Area */
        .dropzone-container {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 32px 20px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .dropzone-container:hover,
        .dropzone-container.dragover {
            border-color: var(--primary-green);
            background: #f0fdf4;
        }

        .dropzone-icon {
            font-size: 2.5rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
            transition: color 0.2s ease;
        }

        .dropzone-container:hover .dropzone-icon {
            color: var(--primary-green);
        }

        .dropzone-text h4 {
            font-family: var(--font-display);
            font-size: 0.95rem;
            margin: 0 0 4px;
            color: var(--text-dark);
        }

        .dropzone-text p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Selected file info strip */
        .file-indicator {
            display: none;
            align-items: center;
            gap: 12px;
            background: #f0fdf4;
            border: 1px solid rgba(11, 143, 58, 0.15);
            padding: 12px 16px;
            border-radius: 8px;
            margin-top: 12px;
            text-align: left;
        }

        .file-indicator i {
            font-size: 1.5rem;
            color: var(--primary-green);
        }

        .file-indicator-details {
            flex-grow: 1;
            min-width: 0;
        }

        .file-indicator-details h5 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-indicator-details span {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-family: var(--font-metrics);
        }

        /* Toggle Selector Buttons */
        .tab-selector {
            display: flex;
            gap: 10px;
            background: #f1f5f9;
            padding: 6px;
            border-radius: 12px;
        }

        .tab-btn {
            flex: 1;
            padding: 10px;
            font-family: var(--font-metrics);
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            background: var(--surface);
            color: var(--primary-green);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        }

        .btn-submit {
            width: 100%;
            font-family: var(--font-metrics);
            font-weight: 700;
            background: var(--primary-green);
            border-radius: 12px;
            padding: 14px;
            border: none;
            color: white;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-submit:hover {
            background: var(--dark-green);
        }

        /* Guidelines layout lists */
        .guide-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .guide-list li {
            font-size: 0.82rem;
            color: var(--text-secondary);
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .guide-list li i {
            font-size: 1rem;
            margin-top: 1px;
            flex-shrink: 0;
        }

        /* CSS Loading Spinner Overlay */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 5000;
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
            flex-direction: column;
            gap: 16px;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 900px) {
            .upload-grid {
                grid-template-columns: 1fr;
                margin: 20px auto;
            }

            .upload-main {
                padding: 24px;
            }
        }
    </style>
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <!-- Loading Screen for OCR Processing -->
    <div id="loadingOverlay" class="spinner-overlay">
        <div class="spinner"></div>
        <div style="font-family: var(--font-display); font-weight: 700; font-size: 1.1rem; text-align: center;">
            Processing Document Scans...
            <span style="display: block; font-size: 0.8rem; font-weight: 400; opacity: 0.8; margin-top: 6px; font-family: var(--font-body);">Performing OCR & compiling pages using OpenAI GPT-4o</span>
        </div>
    </div>

    <main class="container">
        <div class="upload-grid">

            <!-- Main Upload Form Section -->
            <div class="upload-main">
                <h2 style="font-family: var(--font-display); font-weight: 800; font-size: 1.5rem; letter-spacing: -0.02em; margin: 0 0 6px;">Upload Resource</h2>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0 0 28px;">Contribute academic content to your department’s repository.</p>

                <form id="uploadForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="form-group">
                        <label>Book / Document Title</label>
                        <input type="text" name="title" required placeholder="e.g. Fundamental Principles of Computing" value="<?= isset($_POST['title']) ? safe_output($_POST['title']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label>Author / Lecturer</label>
                        <input type="text" name="author" required placeholder="e.g. Dr. Jane Smith" value="<?= isset($_POST['author']) ? safe_output($_POST['author']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label>Department Access Scope</label>
                        <div style="padding: 12px 14px; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 10px; background: #f8fafc; color: var(--text-secondary); font-size: 0.9rem; ">
                            <i class="ri-government-line" style="margin-right: 8px; color: var(--primary-green);"></i>
                            Computer Science only · Department is locked to the NACOS CS library.
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Class Level</label>
                        <select name="level" required>
                            <option value="">Select class level</option>
                            <option value="ND1">ND1</option>
                            <option value="ND2">ND2</option>
                            <option value="ND3">ND3</option>
                            <option value="HND1">HND1</option>
                            <option value="HND2">HND2</option>
                            <option value="HND3">HND3</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Book Cover Image (Optional)</label>
                        <div class="dropzone-container" onclick="triggerFileInput('coverFileField')" id="coverDropzone" style="padding: 20px; min-height: 120px;">
                            <i class="ri-image-add-line dropzone-icon"></i>
                            <div class="dropzone-text">
                                <h4>Upload a custom book cover</h4>
                                <p>JPG, PNG, or WEBP. Leave blank to use the default academic cover.</p>
                            </div>
                            <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" id="coverFileField" style="display: none;" onchange="handleFileSelected(this, 'cover')">
                        </div>
                        <div class="file-indicator" id="coverIndicator">
                            <i class="ri-image-fill"></i>
                            <div class="file-indicator-details">
                                <h5 id="coverIndicatorName">No custom cover selected</h5>
                                <span id="coverIndicatorSize">Default cover will be used if absent</span>
                            </div>
                            <i class="ri-checkbox-circle-fill" style="color: var(--primary-green); font-size: 1.2rem;"></i>
                        </div>
                    </div>

                    <!-- Upload Type Tab Selector -->
                    <div class="form-group">
                        <label>Document Upload Format</label>
                        <div class="tab-selector">
                            <button type="button" class="tab-btn active" id="tabPdf" onclick="switchTab('pdf')">
                                <i class="ri-file-pdf-line"></i> Standard PDF
                            </button>
                            <button type="button" class="tab-btn" id="tabScanned" onclick="switchTab('scanned')">
                                <i class="ri-camera-lens-line"></i> Scanned Images (OCR)
                            </button>
                        </div>
                        <input type="hidden" name="upload_type" id="uploadType" value="pdf">
                    </div>

                    <!-- Standard PDF Drag Area -->
                    <div id="pdfUploadArea" class="form-group">
                        <label>PDF Document</label>
                        <div class="dropzone-container" onclick="triggerFileInput('pdfFileField')" id="pdfDropzone">
                            <i class="ri-upload-cloud-line dropzone-icon"></i>
                            <div class="dropzone-text">
                                <h4>Drag & drop your PDF file here</h4>
                                <p>or click to browse local files (Max 10MB)</p>
                            </div>
                            <input type="file" name="book_file" accept=".pdf" id="pdfFileField" required style="display: none;" onchange="handleFileSelected(this, 'pdf')">
                        </div>
                        <!-- Selected File Indicator Strip -->
                        <div class="file-indicator" id="pdfIndicator">
                            <i class="ri-file-pdf-fill"></i>
                            <div class="file-indicator-details">
                                <h5 id="pdfIndicatorName">document.pdf</h5>
                                <span id="pdfIndicatorSize">0.0 MB</span>
                            </div>
                            <i class="ri-checkbox-circle-fill" style="color: var(--primary-green); font-size: 1.2rem;"></i>
                        </div>
                    </div>

                    <!-- Scanned Images (OCR) Drag Area -->
                    <div id="imageUploadArea" class="form-group" style="display: none;">
                        <label>Document Image Pages (Upload sequence orders of pages)</label>
                        <div class="dropzone-container" onclick="triggerFileInput('imageFileField')" id="imagesDropzone">
                            <i class="ri-image-add-line dropzone-icon"></i>
                            <div class="dropzone-text">
                                <h4>Select multi-page scanned document images</h4>
                                <p>Supports JPG, JPEG, and PNG formats (Max 5MB per page)</p>
                            </div>
                            <input type="file" name="scanned_images[]" accept="image/png, image/jpeg, image/jpg" id="imageFileField" multiple style="display: none;" onchange="handleFileSelected(this, 'images')">
                        </div>
                        <!-- Selected Files Indicator Strip -->
                        <div class="file-indicator" id="imagesIndicator">
                            <i class="ri-folder-image-fill"></i>
                            <div class="file-indicator-details">
                                <h5 id="imagesIndicatorName">0 files selected</h5>
                                <span id="imagesIndicatorSize">Total size: 0.0 MB</span>
                            </div>
                            <i class="ri-checkbox-circle-fill" style="color: var(--primary-green); font-size: 1.2rem;"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="ri-upload-cloud-2-line"></i> Submit Resource for Moderation
                    </button>
                </form>
            </div>

            <!-- Sidebar Info/Guidelines Column -->
            <div class="upload-sidebar">
                <div class="sidebar-card" style="background: linear-gradient(180deg, var(--surface) 0%, #fdfdf0 100%); border-color: rgba(255, 204, 0, 0.25);">
                    <h3 style="color: #b06000;"><i class="ri-lightbulb-line"></i> OCR Guidelines</h3>
                    <ul class="guide-list">
                        <li>
                            <i class="ri-check-line" style="color: var(--primary-green);"></i>
                            <span>Ensure pages are rotated straight to optimize AI reading accuracy.</span>
                        </li>
                        <li>
                            <i class="ri-check-line" style="color: var(--primary-green);"></i>
                            <span>Avoid shadows, low lighting conditions, or extreme glares when capturing images.</span>
                        </li>
                        <li>
                            <i class="ri-check-line" style="color: var(--primary-green);"></i>
                            <span>OpenAI GPT-4o parses handwritten equations and printed fonts natively.</span>
                        </li>
                    </ul>
                </div>

                <div class="sidebar-card">
                    <h3><i class="ri-shield-check-line" style="color: var(--primary-green);"></i> Compliance Checks</h3>
                    <ul class="guide-list">
                        <li>
                            <i class="ri-information-line" style="color: var(--text-secondary);"></i>
                            <span>All uploads are approved automatically and become available once the upload completes.</span>
                        </li>
                        <li>
                            <i class="ri-information-line" style="color: var(--text-secondary);"></i>
                            <span>Uploader accounts and audit logs track user IDs on submissions.</span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Core Component Include -->
    <?php include_once __DIR__ . '/../includes/modal.php'; ?>
    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        // Tab switching controller
        function switchTab(type) {
            const tabPdf = document.getElementById('tabPdf');
            const tabScanned = document.getElementById('tabScanned');
            const pdfArea = document.getElementById('pdfUploadArea');
            const imageArea = document.getElementById('imageUploadArea');
            const uploadType = document.getElementById('uploadType');
            const pdfField = document.getElementById('pdfFileField');
            const imageField = document.getElementById('imageFileField');

            uploadType.value = type;

            if (type === 'pdf') {
                tabPdf.classList.add('active');
                tabScanned.classList.remove('active');
                pdfArea.style.display = 'block';
                imageArea.style.display = 'none';
                pdfField.setAttribute('required', 'required');
                imageField.removeAttribute('required');
            } else {
                tabScanned.classList.add('active');
                tabPdf.classList.remove('active');
                pdfArea.style.display = 'none';
                imageArea.style.display = 'block';
                imageField.setAttribute('required', 'required');
                pdfField.removeAttribute('required');
            }
        }

        // File browser helper
        function triggerFileInput(id) {
            document.getElementById(id).click();
        }

        // Display selected file metrics beautifully
        function handleFileSelected(input, type) {
            const pdfIndicator = document.getElementById('pdfIndicator');
            const imagesIndicator = document.getElementById('imagesIndicator');

            if (type === 'pdf') {
                const file = input.files[0];
                if (file) {
                    document.getElementById('pdfIndicatorName').textContent = file.name;
                    document.getElementById('pdfIndicatorSize').textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                    pdfIndicator.style.display = 'flex';
                } else {
                    pdfIndicator.style.display = 'none';
                }
            } else {
                const files = input.files;
                if (files.length > 0) {
                    let totalSize = 0;
                    for (let i = 0; i < files.length; i++) {
                        totalSize += files[i].size;
                    }
                    document.getElementById('imagesIndicatorName').textContent = files.length + ' file(s) selected';
                    document.getElementById('imagesIndicatorSize').textContent = 'Total size: ' + (totalSize / (1024 * 1024)).toFixed(2) + ' MB';
                    imagesIndicator.style.display = 'flex';
                } else {
                    imagesIndicator.style.display = 'none';
                }
            }
        }

        // Dragover styling handlers
        const dropzones = document.querySelectorAll('.dropzone-container');
        dropzones.forEach(zone => {
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('dragover');
            });
            zone.addEventListener('dragleave', () => {
                zone.classList.remove('dragover');
            });
            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.classList.remove('dragover');

                const inputId = zone.querySelector('input[type="file"]').id;
                const fileInput = document.getElementById(inputId);

                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    const type = inputId === 'pdfFileField' ? 'pdf' : 'images';
                    handleFileSelected(fileInput, type);
                }
            });
        });

        // Safe Client-side validations
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const uploadType = document.getElementById('uploadType').value;

            if (uploadType === 'pdf') {
                const fileInput = document.getElementById('pdfFileField');
                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    if (file.size > 10 * 1024 * 1024) { // 10MB limit
                        e.preventDefault();
                        AppModal.open({
                            type: 'warning',
                            title: 'File Too Large',
                            subtitle: 'Validation Error',
                            message: 'The selected PDF file size exceeds the 10MB limitation policy. Please compress it and try again.',
                            showSecondary: false
                        });
                        return;
                    }
                }
            } else {
                const fileInput = document.getElementById('imageFileField');
                const files = fileInput.files;
                if (files.length > 0) {
                    for (let i = 0; i < files.length; i++) {
                        if (files[i].size > 5 * 1024 * 1024) { // 5MB image limit
                            e.preventDefault();
                            AppModal.open({
                                type: 'warning',
                                title: 'Image Size Exceeded',
                                subtitle: 'Validation Error',
                                message: `The scan page "${files[i].name}" exceeds the 5MB single page limit. Please scale down the resolution and upload again.`,
                                showSecondary: false
                            });
                            return;
                        }
                    }

                    // Display loading screen while uploading and performing OCR
                    document.getElementById('loadingOverlay').style.display = 'flex';
                }
            }
        });

        // Display PHP runtime validation errors inside the custom modal
        window.addEventListener('DOMContentLoaded', () => {
            <?php if (!empty($errors)): ?>
                const errorMessage = <?= json_encode(implode("\n", $errors)) ?>;
                AppModal.open({
                    type: 'error',
                    title: 'Upload Failed',
                    subtitle: 'Validation Errors Detected',
                    message: errorMessage,
                    showSecondary: false
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>