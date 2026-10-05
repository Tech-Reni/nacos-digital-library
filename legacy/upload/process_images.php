<?php
/**
 * upload/process_images.php
 * Phase 1/17: Multiple scanned image upload validation
 */

if (!defined('UPLOAD_ERR_OK')) {
    exit('Direct access not permitted.');
}

$uploaded_paths = [];
$allowed_mimes = ['image/jpeg', 'image/png', 'image/jpg'];
$max_size = 5 * 1024 * 1024; // 5MB per image limit

// Clean out existing sessions
unset($_SESSION['pending_scans']);
unset($_SESSION['ocr_results']);

// Normalize structural files input array
$raw_files = $_FILES['scanned_images'];
$file_count = count($raw_files['name']);

$temp_dir = __DIR__ . '/../uploads/temp_scans/';
if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

for ($i = 0; $i < $file_count; $i++) {
    $tmp_name = $raw_files['tmp_name'][$i];
    $name     = $raw_files['name'][$i];
    $size     = $raw_files['size'][$i];
    $error    = $raw_files['error'][$i];

    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading file: " . htmlspecialchars($name);
        continue;
    }

    if ($size > $max_size) {
        $errors[] = "File '{$name}' is too large. Limit is 5MB.";
        continue;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($tmp_name);

    if (!in_array($mime_type, $allowed_mimes)) {
        $errors[] = "File '{$name}' is not a valid format. Only JPG/PNG images are allowed.";
        continue;
    }

    // Generate secure randomized unique filename
    $ext = ($mime_type === 'image/png') ? 'png' : 'jpg';
    $safe_name = bin2hex(random_bytes(16)) . '.' . $ext;
    $absolute_path = $temp_dir . $safe_name;

    // Compress image before storage (max 2000px width, 80% quality)
    $compressed_path = compress_image($tmp_name, $mime_type, $absolute_path, 2000, 80);
    
    if ($compressed_path && file_exists($compressed_path)) {
        $uploaded_paths[] = [
            'original_name' => basename($name),
            'stored_path' => 'uploads/temp_scans/' . $safe_name,
            'absolute_path' => $compressed_path,
            'original_size' => $size,
            'compressed_size' => filesize($compressed_path)
        ];
    } else {
        $errors[] = "Failed to process image: {$name}";
    }
}

if (empty($errors) && !empty($uploaded_paths)) {
    $_SESSION['pending_scans'] = $uploaded_paths;
}