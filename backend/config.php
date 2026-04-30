<?php
/**
 * UniBite - Database Configuration
 * Σύνδεση με τη βάση δεδομένων
 */

$host = 'localhost';
$port = 3307; // Αλλαξε σε 3306 αν το XAMPP MySQL σου τρεχει στη θυρα 3306 (default)
$dbname = 'unibite_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

// Ρυθμίσεις για sessions
session_start();

/**
 * Βοηθητική συνάρτηση για JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Βοηθητική συνάρτηση για έλεγχο αυθεντικοποίησης
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Απαιτείται σύνδεση'], 401);
    }
}

/**
 * Βοηθητική συνάρτηση για έλεγχο admin role
 */
function requireAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        jsonResponse(['error' => 'Απαιτείται δικαιώματα διαχειριστή'], 403);
    }
}