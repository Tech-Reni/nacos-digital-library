<?php
/**
 * upload/upload_success.php
 * Premium Dashboard Success Screen & Detailed OCR Preview Frame
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

check_auth();

if (!isset($_SESSION['last_uploaded_book'])) {
    header("Location: ../home.php");
    exit;
}

$book = $_SESSION['last_uploaded_book'];
$ocr_results = $_SESSION['ocr_results'] ?? [];

// Clear variables from dynamic sessions
unset($_SESSION['last_uploaded_book']);
unset($_SESSION['ocr_results']);

$page_title = "Upload Success";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php render_meta([
        'title'       => 'Upload Successful | NACOS App',
        'description' => 'Your academic resource was uploaded successfully and is now pending review by the NACOS App moderation team.',
    ]); ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #0b8f3a;
            --text-dark: #111827;
            --text-secondary: #52606d;
            --bg: #f3f7fa;
            --font-display: 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-metrics: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg);
            font-family: var(--font-body);
            color: var(--text-dark);
            margin: 0;
        }

        .container {
            max-width: 800px;
            margin: 48px auto;
            padding: 0 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
            padding: 40px;
            text-align: center;
        }

        .success-icon {
            width: 72px;
            height: 72px;
            background: #f0fdf4;
            color: var(--primary-green);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 24px;
            border: 1px solid rgba(11, 143, 58, 0.15);
        }

        .meta-pill {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
            font-family: var(--font-metrics);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .ocr-preview-container {
            margin-top: 32px;
            text-align: left;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            padding-top: 24px;
        }

        .ocr-preview-container h3 {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1rem;
            margin: 0 0 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ocr-scroller {
            background: #fafafb;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            max-height: 250px;
            overflow-y: auto;
            padding: 18px;
            font-family: var(--font-body);
            font-size: 0.85rem;
            color: #374151;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 36px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 100px;
            text-decoration: none;
            font-family: var(--font-metrics);
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary-green);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #066b2a;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-dark);
            border-color: #cbd5e1;
        }

        .btn-secondary:hover {
            background: #f1f5f9;
        }
    </style>
</head>
<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main class="container">
        <div class="card">
            <div class="success-icon">
                <i class="ri-checkbox-circle-fill"></i>
            </div>
            
            <h1 style="font-family: var(--font-display); font-weight: 800; font-size: 1.8rem; margin: 0 0 8px; letter-spacing: -0.02em;">Upload Successful</h1>
            <p style="color: var(--text-secondary); margin: 0 0 24px; font-size: 0.95rem;">Your resource has been uploaded securely and is awaiting moderation review.</p>

            <div style="display: flex; flex-direction: column; gap: 8px; align-items: center; margin-bottom: 24px;">
                <span style="font-family: var(--font-display); font-weight: 700; font-size: 1.15rem; color: var(--text-dark);"><?= safe_output($book['title']) ?></span>
                <span style="color: var(--text-secondary); font-size: 0.88rem; font-weight: 500;">By <?= safe_output($book['author']) ?></span>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center;">
                <span class="meta-pill"><i class="ri-government-line"></i> <?= safe_output(format_department($book['department'])) ?></span>
                <?php if ($book['is_ocr']): ?>
                    <span class="meta-pill" style="background: #eff6ff; color: #1e40af; border-color: #bfdbfe;"><i class="ri-cpu-line"></i> AI Scanned</span>
                <?php endif; ?>
            </div>

            <!-- OCR Text Preview Container -->
            <?php if ($book['is_ocr'] && !empty($ocr_results)): ?>
            <div class="ocr-preview-container">
                <h3><i class="ri-file-text-line" style="color: var(--primary-green);"></i> Extracted Text Summary</h3>
                <div class="ocr-scroller">
                    <?php foreach ($ocr_results as $ocr): ?>
                        <div style="margin-bottom: 16px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 12px;">
                            <strong style="color: var(--primary-green); font-family: var(--font-metrics); font-size: 0.78rem; text-transform: uppercase; tracking-spacing: 0.05em; display:block; margin-bottom: 6px;">Page <?= $ocr['page'] ?></strong>
                            <p style="margin: 0;"><?= safe_output($ocr['text']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="../home.php" class="btn btn-primary"><i class="ri-dashboard-line"></i> Go to Dashboard</a>
                <a href="upload.php" class="btn btn-secondary"><i class="ri-upload-cloud-2-line"></i> Upload Another</a>
            </div>
        </div>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>