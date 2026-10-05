<?php

/**
 * view/reader.php
 * Phase 9: PDF Access Control with Responsive Zoom & High-Visibility UI
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Ensure user is logged in
check_auth();

$book_uuid = $_GET['uuid'] ?? '';

if (!$book_uuid) {
    die("Invalid request.");
}

// Fetch book details and check if approved (Phase 11: Prepared Statements)
$stmt = $conn->prepare("SELECT b.id, b.title, b.status, b.visibility, bf.storage_key, bf.id as file_id, bf.pages 
                        FROM books b 
                        JOIN book_files bf ON b.id = bf.book_id 
                        WHERE b.uuid = ? LIMIT 1");
$stmt->bind_param("s", $book_uuid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Resource not found.");
}

$book = $result->fetch_assoc();

// Debug: Log book details
error_log("[Reader] Book ID: {$book['id']}, Storage: {$book['storage_key']}, Pages: {$book['pages']}");

// Phase 17: Track Reading History & Views
if (!isset($_SESSION['viewed_books'])) $_SESSION['viewed_books'] = [];

if (!in_array($book['id'], $_SESSION['viewed_books'])) {
    // 1. Increment View Count (using prepared statement to prevent SQL injection)
    $view_stmt = $conn->prepare("INSERT INTO book_views (book_id, view_count) VALUES (?, 1) 
                  ON DUPLICATE KEY UPDATE view_count = view_count + 1");
    $view_stmt->bind_param("i", $book['id']);
    $view_stmt->execute();
    $view_stmt->close();

    // 2. Log to Reading History
    $hist_stmt = $conn->prepare("INSERT INTO reading_history (user_id, book_id, last_opened) 
                                 VALUES (?, ?, NOW()) 
                                 ON DUPLICATE KEY UPDATE last_opened = NOW()");
    $hist_stmt->bind_param("ii", $_SESSION['user_id'], $book['id']);
    $hist_stmt->execute();
    $hist_stmt->close();

    // 3. Log securely to Audit Trail (Executed on PHP backend on page load)
    log_audit($conn, "book_opened", "books", $book['id'], [
        'total_pages' => $book['pages']
    ]);

    $_SESSION['viewed_books'][] = $book['id'];
}

// Phase 9: Access Control Logic
if ($book['status'] !== 'approved' && !is_governor()) {
    die("This resource is awaiting approval.");
}

// Generate a temporary access token (Phase 5: Secure Tokens)
$access_token = bin2hex(random_bytes(32));
$expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));

$token_stmt = $conn->prepare("INSERT INTO pdf_access_tokens (token, book_file_id, user_id, expires_at) VALUES (?, ?, ?, ?)");
$token_stmt->bind_param("siis", $access_token, $book['file_id'], $_SESSION['user_id'], $expires);
$token_stmt->execute();

$page_title = "Reading: " . $book['title'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Read <?= safe_output($book['title']) ?> online in NACOS App. Access academic books and study materials.">
    <title>NACOS App | <?= safe_output($book['title']) ?></title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">

    <!-- FIXED: Added missing Remix Icons CSS stylesheet to resolve empty/invisible icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        body,
        html {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }

        .reader-container {
            height: calc(100vh - 64px);
            width: 100%;
            position: relative;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
            /* Enables scrollbars when canvas overflows due to zoom */
        }

        #pdfCanvas {
            display: block;
            background: white;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.12);
            margin: auto;
            /* Centers canvas when smaller than container, scrolls nicely when larger */
            transition: transform 0.15s ease-out;
        }

        /* Glassmorphic floating pill-bar controls design */
        .pdf-controls {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 8px 18px;
            border-radius: 100px;
            display: inline-flex;
            gap: 12px;
            align-items: center;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }

        /* FIXED: Dynamic high-contrast green & white themed round buttons */
        .pdf-controls button {
            background: var(--primary-green, #0b8f3a);
            color: #ffffff;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(11, 143, 58, 0.2);
        }

        .pdf-controls button:hover:not(:disabled) {
            background: var(--dark-green, #066b2a);
            transform: scale(1.08);
        }

        .pdf-controls button:disabled {
            background: rgba(255, 255, 255, 0.15);
            color: rgba(255, 255, 255, 0.35);
            cursor: not-allowed;
            box-shadow: none;
        }

        .pdf-controls span {
            font-size: 0.85rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            padding: 0 4px;
            letter-spacing: 0.02em;
        }

        /* Vertical separation border */
        .control-divider {
            width: 1px;
            height: 20px;
            background: rgba(255, 255, 255, 0.15);
            margin: 0 4px;
        }

        .error-message {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
        }

        .error-message i {
            font-size: 64px;
            color: #ef4444;
            margin-bottom: 20px;
        }

        .error-message h2 {
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .error-message p {
            color: var(--text-secondary);
            line-height: 1.6;
        }
    </style>
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="reader-container">
        <canvas id="pdfCanvas"></canvas>

        <div class="error-message" id="errorMessage">
            <i class="ri-file-pdf-line"></i>
            <h2>Unable to Load Document</h2>
            <p>This book's PDF file is missing or corrupted. Please contact support or try re-uploading the book.</p>
        </div>

        <!-- Navigation and Zoom Controls bar -->
        <div class="pdf-controls" id="pdfControls" style="display: none;">
            <button onclick="previousPage()" id="prevBtn" disabled title="Previous Page">
                <i class="ri-arrow-left-s-line"></i>
            </button>
            <span><span id="pageInfo">0</span> / <span id="pageCount">0</span></span>
            <button onclick="nextPage()" id="nextBtn" disabled title="Next Page">
                <i class="ri-arrow-right-s-line"></i>
            </button>

            <div class="control-divider"></div>

            <button onclick="zoomOut()" id="zoomOutBtn" title="Zoom Out">
                <i class="ri-zoom-out-line"></i>
            </button>
            <button onclick="resetZoom()" id="zoomResetBtn" title="Fit to Page">
                <i class="ri-aspect-ratio-line"></i>
            </button>
            <button onclick="zoomIn()" id="zoomInBtn" title="Zoom In">
                <i class="ri-zoom-in-line"></i>
            </button>
        </div>
    </div>

    <!-- PDF.js Library - Using stable version -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>

    <script>
        // PDF.js Reader with Progress Tracking
        let pdfDoc = null;
        let currentPage = 1;
        let totalPages = 0;
        const bookId = <?= $book['id'] ?>;
        const userId = <?= $_SESSION['user_id'] ?>;
        const accessToken = '<?= $access_token ?>';
        let readingStartTime = Date.now();
        let lastSavedProgress = 0;
        let pageTurnCount = 0;

        // Zoom state configuration parameters
        let currentZoom = 1.0;
        const ZOOM_STEP = 0.2;
        const MAX_ZOOM = 3.0;
        const MIN_ZOOM = 0.6;

        // Disable right-click on canvas
        const canvas = document.getElementById('pdfCanvas');
        canvas.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });

        // Disable print/save shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && ['p', 's', 'u'].includes(e.key.toLowerCase())) {
                e.preventDefault();
            }
        });

        // Load PDF
        const pdfUrl = '<?= $BASE_URL ?>view/serve_pdf.php?token=' + accessToken;
        console.log('[PDF.js] Loading PDF from:', pdfUrl);

        const loadingTask = pdfjsLib.getDocument(pdfUrl);

        loadingTask.promise.then(function(pdf) {
            console.log('[PDF.js] PDF loaded successfully. Pages:', pdf.numPages);
            pdfDoc = pdf;
            totalPages = pdf.numPages;

            if (totalPages === 0) {
                throw new Error('PDF has 0 pages');
            }

            document.getElementById('pageCount').textContent = totalPages;
            document.getElementById('pdfControls').style.display = 'inline-flex';
            document.getElementById('prevBtn').disabled = false;
            document.getElementById('nextBtn').disabled = false;

            // Render first page
            renderPage(currentPage);

        }).catch(function(error) {
            console.error('[PDF.js] Error loading PDF:', error);

            // Show error message
            document.getElementById('pdfCanvas').style.display = 'none';
            document.getElementById('pdfControls').style.display = 'none';
            document.getElementById('errorMessage').style.display = 'block';
        });

        function renderPage(pageNum) {
            if (!pdfDoc) return;

            pdfDoc.getPage(pageNum).then(function(page) {
                const ctx = canvas.getContext('2d');

                // Calculate scale to fit container
                const container = document.querySelector('.reader-container');
                const containerWidth = container.clientWidth - 40; // padding boundary
                const containerHeight = container.clientHeight - 40; // padding boundary

                const viewport = page.getViewport({
                    scale: 1
                });

                // Calculate responsive scale multiplied by currentZoom matrix
                const baseScale = Math.min(
                    containerWidth / viewport.width,
                    containerHeight / viewport.height
                );
                const scale = baseScale * currentZoom;

                const scaledViewport = page.getViewport({
                    scale: scale
                });

                canvas.width = scaledViewport.width;
                canvas.height = scaledViewport.height;

                const renderContext = {
                    canvasContext: ctx,
                    viewport: scaledViewport
                };

                page.render(renderContext);

                document.getElementById('pageInfo').textContent = pageNum;
                currentPage = pageNum;

                // Track page turn
                pageTurnCount++;
                trackProgress();
            }).catch(err => {
                console.error('[PDF.js] Error rendering page:', err);
            });
        }

        // Zoom Operations handlers
        function zoomIn() {
            if (currentZoom < MAX_ZOOM) {
                currentZoom += ZOOM_STEP;
                renderPage(currentPage);
            }
        }

        function zoomOut() {
            if (currentZoom > MIN_ZOOM) {
                currentZoom -= ZOOM_STEP;
                renderPage(currentPage);
            }
        }

        function resetZoom() {
            currentZoom = 1.0;
            renderPage(currentPage);
        }

        function previousPage() {
            if (currentPage > 1) {
                renderPage(currentPage - 1);
            }
        }

        function nextPage() {
            if (currentPage < totalPages) {
                renderPage(currentPage + 1);
            }
        }

        function trackProgress() {
            const progressPercent = Math.round((currentPage / totalPages) * 100);

            // Save progress if changed by 10% or more
            if (Math.abs(progressPercent - lastSavedProgress) >= 10) {
                saveProgress(progressPercent);
                lastSavedProgress = progressPercent;
            }

            // Mark as completed if 95% or more
            if (progressPercent >= 95 && lastSavedProgress < 95) {
                saveProgress(progressPercent, true);
            }
        }

        function saveProgress(percent, completed = false) {
            const formData = new FormData();
            formData.append('book_id', bookId);
            formData.append('progress_percent', percent);
            formData.append('completed', completed ? '1' : '0');
            formData.append('csrf_token', '<?= csrf_token() ?>');
            formData.append('time_spent', Math.floor((Date.now() - readingStartTime) / 1000));
            formData.append('pages_viewed', pageTurnCount);

            fetch('<?= $BASE_URL ?>view/update_progress.php', {
                method: 'POST',
                body: formData
            }).catch(err => console.log('Progress save failed'));
        }

        // Auto-save progress every 30 seconds
        setInterval(() => {
            if (currentPage > 0 && totalPages > 0) {
                const progressPercent = Math.round((currentPage / totalPages) * 100);
                saveProgress(progressPercent);
            }
        }, 30000);

        // Save on page unload
        window.addEventListener('beforeunload', function() {
            if (currentPage > 0 && totalPages > 0) {
                const progressPercent = Math.round((currentPage / totalPages) * 100);
                saveProgress(progressPercent);
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                previousPage();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                nextPage();
            }
        });
    </script>
</body>

</html>