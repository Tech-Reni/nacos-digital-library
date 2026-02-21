<?php

// ================================
// BASE_URL Logic
// ================================

// 1. ROBUST BASE_URL LOGIC (Works on all PHP versions)
$host = $_SERVER['HTTP_HOST'];
if ($host == 'localhost' || strpos($host, 'localhost') !== false) {
	// LOCALHOST SETTINGS
	$BASE_URL = '/nacos-digital-library/'; 
	$servername = "localhost";
	$username = "root";
	$password = "";
	$dbname = "nacos_library"; 
} else {
	// LIVE SERVER SETTINGS
	// Use the full URL to ensure CSS/JS loads correctly from anywhere
	$BASE_URL = 'https://nacos-library.com/'; 
	$servername = "mysql.nacos-library.com";
	$username = "admin_nacos";
	$password = "programming123";
	$dbname = "nacos-library";
}



// 2. DATABASE CONNECTION
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable friendly error reporting

try {
	$conn = new mysqli($servername, $username, $password, $dbname);
	$conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
	// This prints the exact database error if connection fails
	die("Database Connection Failed: " . $e->getMessage());
}

