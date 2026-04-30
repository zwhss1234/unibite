<?php
/**
 * UniBite - Leaderboard & Stats API
 * Στατιστικά & Leaderboard
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    jsonResponse(['error' => 'Μη επιτρεπόμενη μέθοδος'], 405);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'leaderboard':
        getLeaderboard();
        break;
    case 'stats':
        getStats();
        break;
    case 'user-stats':
        requireAuth();
        getUserStats();
        break;
    default:
        jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
}

/**
 * Λήψη Leaderboard (Δ2)
 */
function getLeaderboard() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.username, COUNT(r.id) as total_given
            FROM users u
            JOIN ads a ON u.id = a.cook_id
            JOIN requests r ON a.id = r.ad_id
            WHERE r.status = 'picked_up'
            GROUP BY u.id
            ORDER BY total_given DESC
            LIMIT 10
        ");
        $leaderboard = $stmt->fetchAll();
        
        jsonResponse(['leaderboard' => $leaderboard]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Γενικά στατιστικά
 */
function getStats() {
    global $pdo;
    try {
        // Συνολικά γεύματα τον τελευταίο μήνα
        $stmt = $pdo->query("
            SELECT COUNT(*) as successful_meals
            FROM requests 
            WHERE status = 'picked_up' 
            AND received_at >= NOW() - INTERVAL 1 MONTH
        ");
        $stats = $stmt->fetch();
        
        // Ενεργές αγγελίες
        $stmt = $pdo->query("
            SELECT COUNT(*) as active_ads
            FROM ads 
            WHERE created_at >= NOW() - INTERVAL 48 HOUR
            AND available_portions > 0
        ");
        $active = $stmt->fetch();
        
        // Συνολικοί χρήστες
        $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
        $users = $stmt->fetch();
        
        jsonResponse([
            'stats' => [
                'successful_meals' => intval($stats['successful_meals']),
                'active_ads' => intval($active['active_ads']),
                'total_users' => intval($users['total_users'])
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Στατιστικά συγκεκριμένου χρήστη
 */
function getUserStats() {
    global $pdo;
    $userId = $_SESSION['user_id'];
    
    try {
        // Ως μάγειρας: πόσα έδωσε
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as given
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            WHERE a.cook_id = ? AND r.status = 'picked_up'
        ");
        $stmt->execute([$userId]);
        $given = $stmt->fetch();
        
        // Ως καταναλωτής: πόσα πήρε
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as received
            FROM requests 
            WHERE consumer_id = ? AND status = 'picked_up'
        ");
        $stmt->execute([$userId]);
        $received = $stmt->fetch();
        
        // Μέση βαθμολογία που έδωσε
        $stmt = $pdo->prepare("
            SELECT AVG(rating) as avg_rating
            FROM requests 
            WHERE consumer_id = ? AND rating IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $avgGiven = $stmt->fetch();
        
        // Μέση βαθμολογία που πήρε (αν είναι μάγειρας)
        $stmt = $pdo->prepare("
            SELECT AVG(r.rating) as avg_received
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            WHERE a.cook_id = ? AND r.rating IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $avgReceived = $stmt->fetch();
        
        jsonResponse([
            'user_stats' => [
                'given' => intval($given['given']),
                'received' => intval($received['received']),
                'avg_rating_given' => $avgGiven['avg_rating'] ? round($avgGiven['avg_rating'], 1) : null,
                'avg_rating_received' => $avgReceived['avg_received'] ? round($avgReceived['avg_received'], 1) : null
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}