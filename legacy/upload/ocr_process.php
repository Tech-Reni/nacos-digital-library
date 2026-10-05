<?php
/**
 * upload/ocr_process.php
 * Production-ready server-side OCR processing using OpenAI Vision API.
 * Can be used as:
 * 1. Direct API endpoint (returns JSON)
 * 2. Included file (returns results to session)
 */

// Enable error reporting for logs but suppress output to prevent breaking JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check if being called directly (API mode) or included (upload mode)
$is_direct_call = !defined('OCR_PROCESS_INCLUDED');

if ($is_direct_call) {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/auth_guard.php';
    check_auth();
    header('Content-Type: application/json');
}

$response = [
    'status' => 'error',
    'message' => 'An unexpected server error occurred.'
];

// 1. Check for active scans in the session
if (!isset($_SESSION['pending_scans']) || empty($_SESSION['pending_scans'])) {
    if ($is_direct_call) {
        $response['message'] = 'No pending scanned images found in session. Please upload images first.';
        echo json_encode($response);
        exit;
    } else {
        $errors[] = "No pending scanned images found in session.";
        return;
    }
}

// 2. Resolve OpenAI API Key from environment variable
$api_key = getenv('OPENAI_API_KEY') ?: $_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? null;

$scans = $_SESSION['pending_scans'];
$extracted_text_blocks = [];

// If no API key, skip OCR and return success with empty results
// This allows uploads to proceed without OCR functionality
if (!$api_key) {
    error_log("[OCR Info] OpenAI API Key not configured. Skipping OCR processing.");
    // Return empty OCR results - PDF will still be compiled
    if ($is_direct_call) {
        $response = [
            'status' => 'success',
            'message' => 'OCR skipped (API not configured). Document will be compiled without text extraction.',
            'data' => []
        ];
        echo json_encode($response);
        exit;
    } else {
        // When included, just set session and return
        $_SESSION['ocr_results'] = [];
        return;
    }
}

    try {
        $ocr_failed = false;
        $failed_pages = [];
        
        foreach ($scans as $index => $scan) {
            $abs_path = $scan['absolute_path'] ?? null;

            if (!$abs_path || !file_exists($abs_path)) {
                error_log("[OCR Warning] File not found or inaccessible: " . ($abs_path ?: 'Unknown path'));
                $ocr_failed = true;
                $failed_pages[] = $index + 1;
                continue; 
            }

            // Call OpenAI Vision processing
            $text = process_image_with_openai_vision($abs_path, $api_key);

            if ($text === null || empty(trim($text))) {
                error_log("[OCR Warning] Empty or failed OCR result for page " . ($index + 1));
                $ocr_failed = true;
                $failed_pages[] = $index + 1;
                // Add empty text to maintain page alignment
                $extracted_text_blocks[] = [
                    'page' => $index + 1,
                    'image_path' => $scan['stored_path'],
                    'text' => ''
                ];
                continue;
            }

            $extracted_text_blocks[] = [
                'page' => $index + 1,
                'image_path' => $scan['stored_path'],
                'text' => trim($text)
            ];
        }
        
        // Warn if some pages failed but continue
        if ($ocr_failed) {
            error_log("[OCR Warning] Some pages failed OCR: " . implode(', ', $failed_pages));
        }

        // Save OCR results to session to prepare for compile_pdf.php
        $_SESSION['ocr_results'] = $extracted_text_blocks;

    // Success - save to session
    $_SESSION['ocr_results'] = $extracted_text_blocks;
    
    if ($is_direct_call) {
        $response = [
            'status' => 'success',
            'message' => 'OCR extraction executed successfully.',
            'data' => $extracted_text_blocks
        ];
        echo json_encode($response);
        exit;
    } else {
        // When included, just return without output
        return;
    }

} catch (Exception $e) {
    error_log("[OCR Exception] " . $e->getMessage());
    
    if ($is_direct_call) {
        $response = [
            'status' => 'error',
            'message' => 'OCR pipeline failed: ' . $e->getMessage()
        ];
        echo json_encode($response);
        exit;
    } else {
        // When included, set error in session and return
        $_SESSION['ocr_results'] = [];
        $errors[] = "OCR processing failed: " . $e->getMessage();
        return;
    }
}

/**
 * Sends image data to OpenAI Vision API and returns raw extracted text
 *
 * @param string $image_path Absolute path to local image file
 * @param string $api_key OpenAI API secret key
 * @return string|null Raw text output on success, or null on failure
 */
function process_image_with_openai_vision($image_path, $api_key) {
    // 1. Read and validate local file
    $file_bytes = @file_get_contents($image_path);
    if ($file_bytes === false) {
        error_log("[OCR API Error] Unable to read file content at path: {$image_path}");
        return null;
    }

    $image_data = base64_encode($file_bytes);
    $mime_type = mime_content_type($image_path);

    // 2. Prepare OpenAI API request payload
    $payload = [
        "model" => "gpt-4o",
        "messages" => [
            [
                "role" => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "You are an expert OCR engine. Extract all readable handwritten or printed text from this document image precisely. Preserve paragraphs, alignments, tables, and spacing without altering, formatting, or commenting on them. Output raw extracted text only."
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:{$mime_type};base64,{$image_data}"
                        ]
                    ]
                ]
            ]
        ],
        "max_tokens" => 3000
    ];

    // 3. Initialize and configure cURL
    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer {$api_key}"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60, // Production-grade explicit timeout limit (60s)
        CURLOPT_CONNECTTIMEOUT => 15, // Connection phase timeout limit (15s)
        CURLOPT_SSL_VERIFYPEER => true // Enforce secure SSL validation in production
    ]);

    $response_body = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 4. Evaluate network errors
    if ($curl_error) {
        error_log("[OCR cURL Error] Network transfer failed: {$curl_error}");
        return null;
    }

    // 5. Evaluate HTTP responses
    if ($http_code !== 200) {
        error_log("[OCR API Error] OpenAI API returned HTTP code {$http_code}. Response: {$response_body}");
        return null;
    }

    // 6. Parse response payload
    $result = json_decode($response_body, true);
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    }

    error_log("[OCR Parser Error] Unexpected API payload response schema: " . substr($response_body, 0, 500));
    return null;
}