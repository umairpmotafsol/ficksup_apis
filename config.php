<?php
// ─────────────────────────────────────────────────────────────────────────────
// config.php - Database Configuration
// ─────────────────────────────────────────────────────────────────────────────

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'u906199421_root');
define('DB_PASS', 'String@1919'); // Replace with your actual Hostinger DB password
define('DB_NAME', 'u906199421_fickup_db');

// API Configuration
define('API_URL', 'https://oneaimsolution.com/client/Ficksup/api');
define('ASSETS_URL', 'https://oneaimsolution.com/client/Ficksup/uploads');

// Create database connection
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper Functions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Send JSON response
 */
function jsonResponse($success = true, $data = [], $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Validate request method
 */
function validateMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        jsonResponse(false, [], "Method {$_SERVER['REQUEST_METHOD']} not allowed", 405);
    }
}

/**
 * Get JSON input from request body
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Validate required fields
 */
function validateRequired($data, $required_fields) {
    $missing = [];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';

    return !empty($token) && strpos($token, 'Bearer ') === 0;
}

/**
 * Require admin access
 */
function requireAdmin() {
    if (!isAdmin()) {
        jsonResponse(false, [], 'Unauthorized: Admin access required', 403);
    }
}

/**
 * Allow CORS
 */
function allowCORS() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }
}

allowCORS();
?>