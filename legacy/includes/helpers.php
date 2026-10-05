<?php

/**
 * Foundation Helper Functions
 * Part of Phase 10 (XSS) & Phase 5 (CSRF)
 */

if (!function_exists('safe_output')) {
    /**
     * Escape HTML output to prevent XSS.
     */
    function safe_output($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate or retrieve a CSRF token.
     */
    function csrf_token() {
        if (function_exists('secure_session_start')) {
            secure_session_start();
        } elseif (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Verify a CSRF token from POST request.
     */
    function csrf_verify() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die("CSRF token validation failed. Unauthorized request.");
        }
    }
}

/**
 * Matric Number Validator
 * Phase 2 Requirement
 * Formats: F/ND/24/1234567, P/HND/21/1234567, C/HD/19/1234567,
 * or the legacy ND/YYYY/DEPT/1234 format.
 */
function validate_matric_number($matric) {
    $matric = strtoupper(trim($matric));
    $pattern = '/^(?:[FPC]\/(?:ND|HND|HD)\/(?:1[9]|[2-9][0-9])\/[0-9]+|(?:ND|HND)\/[0-9]{4}\/[A-Z]{2,4}\/[0-9]{3,6})$/';
    return preg_match($pattern, $matric);
}

/**
 * Password Strength Validator
 * Phase 3 Requirement
 * Min 8 chars, Upper, Lower, Number, Special
 */
function is_password_strong($password) {
    $length = strlen($password);
    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasLower = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[\W_]/', $password);
    
    return ($length >= 8 && $hasUpper && $hasLower && $hasNumber && $hasSpecial);
}

/**
 * Dashboard Helpers
 * Phase 17 Requirements
 */

function get_greeting() {
    $hour = (int)(new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->format('G');
    if ($hour < 12) return "Good morning";
    if ($hour < 17) return "Good afternoon";
    return "Good evening";
}

function normalize_department($department) {
    $department = strtolower(trim((string)$department));
    $department = preg_replace('/\s+/', ' ', $department);
    $aliases = [
        'computer science' => 'computer-science',
        'computer-science' => 'computer-science',
        'cs' => 'computer-science',
        'mass communication' => 'mass-communication',
        'mass-communication' => 'mass-communication',
        'mc' => 'mass-communication',
        'accountancy' => 'accountancy',
        'accounting' => 'accountancy',
        'acc' => 'accountancy',
    ];
    return $aliases[$department] ?? str_replace(' ', '-', $department);
}

function format_department($department) {
    $labels = [
        'computer-science' => 'Computer Science',
        'mass-communication' => 'Mass Communication',
        'accountancy' => 'Accountancy',
    ];
    $key = normalize_department($department);
    return $labels[$key] ?? ucwords(str_replace('-', ' ', $key));
}

function normalize_programme($programme) {
    $programme = strtolower(trim((string)$programme));
    $programme = preg_replace('/\s+/', ' ', $programme);
    $aliases = [
        'full time' => 'Full-time',
        'full-time' => 'Full-time',
        'part time' => 'Part-time',
        'part-time' => 'Part-time',
        'codfel' => 'CODFEL',
    ];
    return $aliases[$programme] ?? ucwords($programme);
}

function get_user_stats($conn, $user_id) {
    $stats = [
        'books_read' => 0,
        'bookmarks' => 0,
        'uploads' => 0,
        'approved' => 0,
        'pending' => 0,
        'notifications' => 0
    ];

    // Counts from various tables using prepared statements
    $queries = [
        'books_read' => "SELECT COUNT(*) FROM reading_history WHERE user_id = ?",
        'bookmarks' => "SELECT COUNT(*) FROM bookmarks WHERE user_id = ?",
        'uploads' => "SELECT COUNT(*) FROM books WHERE uploader_id = ?",
        'approved' => "SELECT COUNT(*) FROM books WHERE uploader_id = ? AND status = 'approved'",
        'pending' => "SELECT COUNT(*) FROM books WHERE uploader_id = ? AND status = 'pending'",
        'notifications' => "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
    ];

    foreach ($queries as $key => $sql) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_row();
            $stats[$key] = $row[0];
            $stmt->close();
        }
    }

    return $stats;
}

function get_recommendations($conn, $dept, $level, $limit = 4) {
    $limit = intval($limit);
    if ($limit < 1) {
        $limit = 4;
    }

    // Return random books that match the user's class level.
    // Preference 1: random books from the user's department + level.
    // Preference 2 (fills any remaining slots): random books of the same level
    // from any department, so the section is never empty for the user's level.
    $sql = "(SELECT b.id, b.uuid, b.title, b.author, b.thumbnail_path, b.department, b.level
             FROM books b
             WHERE b.status = 'approved' AND b.department = ? AND b.level = ?
             ORDER BY RAND()
             LIMIT ?)
            UNION ALL
            (SELECT b.id, b.uuid, b.title, b.author, b.thumbnail_path, b.department, b.level
             FROM books b
             WHERE b.status = 'approved' AND b.level = ?
               AND NOT (b.department = ? AND b.level = ?)
             ORDER BY RAND()
             LIMIT ?)
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Fallback: simple random pick by level if prepare fails
        return $conn->query("SELECT b.id, b.uuid, b.title, b.author, b.thumbnail_path, b.department, b.level
                             FROM books b
                             WHERE b.status = 'approved' AND b.level = '" . $conn->real_escape_string($level) . "'
                             ORDER BY RAND() LIMIT " . $limit);
    }

    $stmt->bind_param("ssissiii", $dept, $level, $limit, $level, $dept, $level, $limit, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

function get_recent_activity($conn, $user_id, $limit = 5) {
    $stmt = $conn->prepare("SELECT action, meta, created_at 
                            FROM audit_logs 
                            WHERE user_id = ? 
                            ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

function get_trending_books($conn, $limit = 4) {
    // Use cached results or optimized query instead of RAND()
    // For better performance, we use views DESC which is indexed
    return $conn->query("SELECT b.uuid, b.title, b.author, b.thumbnail_path, b.department, IFNULL(bv.view_count, 0) as views 
                         FROM books b 
                         LEFT JOIN book_views bv ON b.id = bv.book_id 
                         WHERE b.status = 'approved' 
                         ORDER BY views DESC, b.created_at DESC LIMIT " . intval($limit));
}

/**
 * Audit Logging Helper
 * Phase 13 Requirement
 */
function log_audit($conn, $action, $object_type = null, $object_id = null, $meta = null) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'];
    $meta_json = $meta ? json_encode($meta) : null;

    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, object_type, object_id, ip, meta) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ississ", $user_id, $action, $object_type, $object_id, $ip, $meta_json);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Compress image before storage
 * Reduces file size while maintaining quality
 * 
 * @param string $source_path Path to source image
 * @param string $mime_type MIME type of the image
 * @param string $dest_path Destination path for compressed image
 * @param int $max_width Maximum width in pixels (maintains aspect ratio)
 * @param int $quality JPEG quality (0-100)
 * @return string|false Path to compressed image or false on failure
 */
function compress_image($source_path, $mime_type, $dest_path, $max_width = 2000, $quality = 80) {
    // Check if GD library is available
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatetruecolor')) {
        error_log("[Image Compression] GD library not available, copying file without compression");
        // Fallback: just copy the file without compression
        if (copy($source_path, $dest_path)) {
            return $dest_path;
        }
        return false;
    }
    
    try {
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return false;
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        $mime = $image_info['mime'];
        
        // Calculate new dimensions maintaining aspect ratio
        if ($width > $max_width) {
            $ratio = $max_width / $width;
            $new_width = $max_width;
            $new_height = (int)($height * $ratio);
        } else {
            $new_width = $width;
            $new_height = $height;
        }
        
        // Create image from source
        $source_image = false;
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                if (function_exists('imagecreatefromjpeg')) {
                    $source_image = @imagecreatefromjpeg($source_path);
                }
                break;
            case 'image/png':
                if (function_exists('imagecreatefrompng')) {
                    $source_image = @imagecreatefrompng($source_path);
                }
                break;
            default:
                return false;
        }
        
        if (!$source_image) {
            error_log("[Image Compression] Could not create source image from: {$source_path}");
            // Fallback: copy without compression
            if (copy($source_path, $dest_path)) {
                return $dest_path;
            }
            return false;
        }
        
        // Create new resized image
        $resized_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG
        if ($mime === 'image/png' && function_exists('imagealphablending')) {
            imagealphablending($resized_image, false);
            imagesavealpha($resized_image, true);
            $transparent = imagecolorallocatealpha($resized_image, 0, 0, 0, 127);
            imagefilledrectangle($resized_image, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Resize image
        imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        // Save compressed image
        $saved = false;
        if ($mime === 'image/png' && function_exists('imagepng')) {
            $saved = imagepng($resized_image, $dest_path, 8); // PNG compression level 0-9
        } elseif (function_exists('imagejpeg')) {
            $saved = imagejpeg($resized_image, $dest_path, $quality);
        }
        
        // Clean up
        if ($source_image) {
            imagedestroy($source_image);
        }
        if ($resized_image) {
            imagedestroy($resized_image);
        }
        
        if (!$saved) {
            error_log("[Image Compression] Failed to save compressed image, using original");
            // Fallback: copy original
            if (copy($source_path, $dest_path)) {
                return $dest_path;
            }
            return false;
        }
        
        return $dest_path;
        
    } catch (Exception $e) {
        error_log("[Image Compression] Error: " . $e->getMessage());
        // Fallback: copy original file
        if (copy($source_path, $dest_path)) {
            return $dest_path;
        }
        return false;
    }
}

/**
 * Clean up uploaded files on error
 * Removes temporary files and compiled PDFs when database operations fail
 */
function cleanup_uploaded_files($upload_type, $target_path) {
    // Clean up compiled PDF
    if (file_exists($target_path)) {
        @unlink($target_path);
    }
    
    // Clean up temporary scanned images
    if ($upload_type === 'scanned' && isset($_SESSION['pending_scans'])) {
        foreach ($_SESSION['pending_scans'] as $scan) {
            if (isset($scan['absolute_path']) && file_exists($scan['absolute_path'])) {
                @unlink($scan['absolute_path']);
            }
        }
        unset($_SESSION['pending_scans'], $_SESSION['ocr_results'], $_SESSION['compiled_file_size'], $_SESSION['compiled_page_count']);
    }
    
    error_log("[Cleanup] Removed uploaded files after database failure");
}

/**
 * Generate Book Thumbnail
 * Creates cover image from PDF or scanned images
 * Simplified: Only uses Imagick, otherwise uses default placeholder
 */
function generate_book_thumbnail($conn, $book_id, $file_path, $upload_type = 'pdf', $first_image_path = null) {
    // Only process if Imagick is available
    if (!class_exists('\Imagick')) {
        error_log("[Thumbnail] Imagick not available, using default placeholder");
        return false;
    }
    
    $thumbnail_dir = __DIR__ . '/../uploads/protected_books/thumbnails/';
    if (!is_dir($thumbnail_dir)) {
        mkdir($thumbnail_dir, 0755, true);
    }
    
    $thumbnail_filename = 'thumb_' . $book_id . '.jpg';
    $thumbnail_path = $thumbnail_dir . $thumbnail_filename;
    $thumbnail_url = 'uploads/protected_books/thumbnails/' . $thumbnail_filename;
    
    try {
        $imagick = new \Imagick();
        
        if ($upload_type === 'pdf') {
            $full_path = __DIR__ . '/../uploads/protected_books/' . $file_path;
            if (!file_exists($full_path)) {
                error_log("[Thumbnail] PDF file not found: {$full_path}");
                $imagick->clear();
                $imagick->destroy();
                return false;
            }
            $imagick->setResolution(150, 150);
            $imagick->readImage($full_path . '[0]');
        } elseif ($first_image_path && file_exists(__DIR__ . '/../' . $first_image_path)) {
            $imagick->readImage(__DIR__ . '/../' . $first_image_path);
        } else {
            error_log("[Thumbnail] No valid image source for book ID: {$book_id}");
            $imagick->clear();
            $imagick->destroy();
            return false;
        }
        
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(85);
        $imagick->resizeImage(300, 400, \Imagick::FILTER_LANCZOS, 1);
        $imagick->writeImage($thumbnail_path);
        $imagick->clear();
        $imagick->destroy();
        
        // Update database
        $stmt = $conn->prepare("UPDATE books SET thumbnail_path = ? WHERE id = ?");
        $stmt->bind_param("si", $thumbnail_url, $book_id);
        $stmt->execute();
        $stmt->close();
        
        return true;
        
    } catch (Exception $e) {
        error_log("[Thumbnail] Failed: " . $e->getMessage());
        return false;
    }
}
