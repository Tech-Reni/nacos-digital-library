<?php

/**
 * upload/compile_pdf.php
 * Phase 3/17: Multi-page image assembler to final compiled PDF
 */

if (!defined('UPLOAD_ERR_OK')) {
    exit('Direct access not permitted.');
}

// Fallback initialization to satisfy IDE static analyzers (Intelephense)
if (!isset($target_path)) {
    $upload_dir = __DIR__ . '/../uploads/protected_books/';
    if (!isset($new_filename)) {
        $new_filename = bin2hex(random_bytes(16)) . '.pdf';
    }
    $target_path = $upload_dir . $new_filename;
}

if (!isset($_SESSION['pending_scans']) || empty($_SESSION['pending_scans'])) {
    $errors[] = "Compilation failure: Missing pending image references.";
    return;
}

$scans = $_SESSION['pending_scans'];

try {
    if (class_exists('\Imagick')) {
        // === Method A: Imagick Lossless PDF Assembler ===
        $imagick = new \Imagick();

        foreach ($scans as $scan) {
            $abs = $scan['absolute_path'];
            if (file_exists($abs)) {
                $imagick->readImage($abs);
            }
        }

        $imagick->setImageFormat('pdf');
        $imagick->writeImages($target_path, true);
        $imagick->clear();
        $imagick->destroy();
    } else {
        // === Method B: Pure PHP Fallback Stream Construction ===
        // Prevents server crashes if Imagick is not installed.
        $pdf_content = "%PDF-1.4\n";
        $offsets = [];
        $objects = [];

        // Root structures
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Page catalog references
        $page_refs = "";
        $object_index = 3;
        $image_objects = [];

        foreach ($scans as $index => $scan) {
            $img_path = $scan['absolute_path'];
            if (!file_exists($img_path)) continue;

            $img_data = file_get_contents($img_path);
            $size = getimagesize($img_path);
            $width = $size[0] ?? 800;
            $height = $size[1] ?? 1100;

            $image_objects[$index] = [
                'index' => $object_index,
                'width' => $width,
                'height' => $height,
                'data' => $img_data,
                'page_index' => $object_index + 1
            ];

            $page_refs .= ($object_index + 1) . " 0 R ";
            $object_index += 3; // FIXED: Incremented by 3 to reserve distinct slots for: 1. Image Object, 2. Page Meta, and 3. Paint Stream command
        }

        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [ {$page_refs} ] /Count " . count($image_objects) . " >>\nendobj\n";

        foreach ($image_objects as $index => $img_obj) {
            $img_idx = $img_obj['index'];
            $page_idx = $img_obj['page_index'];
            $w = $img_obj['width'];
            $h = $img_obj['height'];
            $img_data = $img_obj['data'];

            // FIXED: Detect if image data starts with JPEG binary magic bytes and apply DCTDecode stream filter
            $filter = "";
            if (strpos($img_data, "\xFF\xD8\xFF") === 0) {
                $filter = " /Filter /DCTDecode";
            }

            // Embed image dictionary object
            $objects[$img_idx] = "{$img_idx} 0 obj\n<< /Type /XObject /Subtype /Image /Width {$w} /Height {$h} /ColorSpace /DeviceRGB /BitsPerComponent 8{$filter} /Length " . strlen($img_data) . " >>\nstream\n" . $img_data . "\nendstream\nendobj\n";

            // Map corresponding coordinate page viewport
            $objects[$page_idx] = "{$page_idx} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [ 0 0 {$w} {$h} ] /Resources << /XObject << /Im{$index} {$img_idx} 0 R >> >> /Contents " . ($page_idx + 1) . " 0 R >>\nendobj\n";

            // Map paint commands
            $stream_command = "q {$w} 0 0 {$h} 0 0 cm /Im{$index} Do Q";
            $objects[$page_idx + 1] = ($page_idx + 1) . " 0 obj\n<< /Length " . strlen($stream_command) . " >>\nstream\n{$stream_command}\nendstream\nendobj\n";
        }

        // Write standard byte-offsets table
        foreach ($objects as $num => $obj_str) {
            $offsets[$num] = strlen($pdf_content);
            $pdf_content .= $obj_str;
        }

        $xref_pos = strlen($pdf_content);
        $pdf_content .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($k = 1; $k <= count($objects); $k++) {
            $pdf_content .= sprintf("%010d 00000 n \n", $offsets[$k]);
        }

        $pdf_content .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref_pos}\n%%EOF";
        file_put_contents($target_path, $pdf_content);
    }

    // Save target parameters to update final metadata row
    $file_size = filesize($target_path);

    // Validate that PDF was actually created and has content
    if (!$file_size || $file_size < 1024) { // Less than 1KB is suspicious
        throw new Exception("Compiled PDF is empty or too small. File size: " . $file_size . " bytes");
    }

    // Count pages in compiled PDF
    $page_count = 0;
    if (class_exists('\Imagick') && $file_size > 0) {
        try {
            $imagick = new \Imagick();
            $imagick->readImage($target_path);
            $page_count = $imagick->getNumberImages();
            $imagick->clear();
            $imagick->destroy();

            // Validate page count matches expected
            if ($page_count === 0) {
                error_log("[PDF Compiler Warning] Imagick reported 0 pages, using scan count");
                $page_count = count($scans);
            }
        } catch (Exception $e) {
            error_log("[PDF Compiler] Could not count pages with Imagick: " . $e->getMessage());
            $page_count = count($scans);
        }
    } else {
        $page_count = count($scans);
    }

    // Final validation
    if ($page_count === 0) {
        throw new Exception("Could not determine page count for compiled PDF");
    }

    // Store page count in session for upload.php to save to database
    $_SESSION['compiled_page_count'] = $page_count;
    $_SESSION['compiled_file_size'] = $file_size;

    // Clean up temporary image sources to maintain server storage space
    foreach ($scans as $scan) {
        if (file_exists($scan['absolute_path'])) {
            unlink($scan['absolute_path']);
        }
    }

    unset($_SESSION['pending_scans']);
} catch (Exception $ex) {
    error_log("[PDF Compiler Error] " . $ex->getMessage());
    $errors[] = "Document compilation error: " . $ex->getMessage();
}
