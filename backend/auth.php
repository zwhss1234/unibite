<?php
/**
 * UniBite - Authentication API
 * Διαχείριση χρηστών (Εγγραφή/Σύνδεση)
 */

require_once 'config.php';

// Λήψη της μεθόδου αιτήματος
$method = $_SERVER['REQUEST_METHOD'];

// Δρομολόγηση ανάλογα με τη μέθοδο
switch ($method) {
    case 'POST':
        handlePost();
        break;
    case 'GET':
        handleGet();
        break;
    default:
        jsonResponse(['error' => 'Μη επιτρεπόμενη μέθοδος'], 405);
}

function handlePost() {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'register':
            registerUser();
            break;
        case 'login':
            loginUser();
            break;
        case 'logout':
            logoutUser();
            break;
        default:
            jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
    }
}

function handleGet() {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'me') {
        getCurrentUser();
    } else {
        jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
    }
}

/**
 * Εγγραφή νέου χρήστη
 */
function registerUser() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Υποχρεωτικά πεδία
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $role = $input['role'] ?? 'consumer';
    
    // Έλεγχος υποχρεωτικών πεδίων
    if (empty($username) || empty($email)) {
        jsonResponse(['error' => 'Username και Email είναι υποχρεωτικά'], 400);
    }
    
    // Έλεγχος έγκυρου role
    if (!in_array($role, ['cook', 'consumer', 'admin'])) {
        jsonResponse(['error' => 'Μη έγκυρος ρόλος'], 400);
    }
    
    try {
        // Έλεγχος αν υπάρχει ήδη το email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Το email υπάρχει ήδη'], 400);
        }
        
        // Έλεγχος αν υπάρχει ήδη το username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Το username υπάρχει ήδη'], 400);
        }
        
        // Εγγραφή χρήστη με 5 δωρεάν credits (Γ2)
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, role, credits) 
            VALUES (?, ?, ?, 5)
        ");
        $stmt->execute([$username, $email, $role]);
        
        $userId = $pdo->lastInsertId();
        
        jsonResponse([
            'message' => 'Εγγραφή επιτυχής!',
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'credits' => 5
            ]
        ], 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Σύνδεση χρήστη
 */
function loginUser() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = trim($input['email'] ?? '');
    
    if (empty($email)) {
        jsonResponse(['error' => 'Email είναι υποχρεωτικό'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, email, role, credits 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'Χρήστης δεν βρέθηκε'], 404);
        }
        
        // Δημιουργία session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['credits'] = $user['credits'];
        
        jsonResponse([
            'message' => 'Σύνδεση επιτυχής!',
            'user' => $user
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Αποσύνδεση χρήστη
 */
function logoutUser() {
    session_destroy();
    jsonResponse(['message' => 'Αποσύνδεση επιτυχής']);
}

/**
 * Λήψη τρέχοντος χρήστη
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Δεν είστε συνδεδεμένοι'], 401);
    }
    
    jsonResponse([
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role'],
        'credits' => $_SESSION['credits'] ?? 0
    ]);
}