<?php
/**
 * UniBite - Stats & Leaderboard API
 * Endpoints: leaderboard, stats, user-stats
 */

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Μη επιτρεπόμενη μέθοδος'], 405);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'leaderboard': getLeaderboard(); break;
    case 'stats':       getStats();       break;
    case 'user-stats':  requireAuth(); getUserStats(); break;
    default:            jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
}

// Top 10 μάγειρες κατά μερίδες που παραδόθηκαν
function getLeaderboard() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.username, COUNT(r.id) AS total_given
            FROM users u
            JOIN ads a ON u.id = a.cook_id
            JOIN requests r ON a.id = r.ad_id
            WHERE r.status = 'picked_up'
            GROUP BY u.id
            ORDER BY total_given DESC
            LIMIT 10
        ");
        jsonResponse(['leaderboard' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Γενικά στατιστικά της πλατφόρμας
function getStats() {
    global $pdo;
    try {
        $meals = $pdo->query("
            SELECT COUNT(*) AS successful_meals FROM requests
            WHERE status = 'picked_up' AND received_at >= NOW() - INTERVAL 1 MONTH
        ")->fetch();

        $active = $pdo->query("
            SELECT COUNT(*) AS active_ads FROM ads
            WHERE created_at >= NOW() - INTERVAL 48 HOUR AND available_portions > 0
        ")->fetch();

        $users = $pdo->query("SELECT COUNT(*) AS total_users FROM users")->fetch();

        jsonResponse([
            'stats' => [
                'successful_meals' => intval($meals['successful_meals']),
                'active_ads'       => intval($active['active_ads']),
                'total_users'      => intval($users['total_users']),
            ],
        ]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Στατιστικά συγκεκριμένου χρήστη
function getUserStats() {
    global $pdo;
    $userId = $_SESSION['user_id'];

    try {
        $given = $pdo->prepare("
            SELECT COUNT(*) AS given FROM requests r
            JOIN ads a ON r.ad_id = a.id
            WHERE a.cook_id = ? AND r.status = 'picked_up'
        ");
        $given->execute([$userId]);

        $received = $pdo->prepare("
            SELECT COUNT(*) AS received FROM requests
            WHERE consumer_id = ? AND status = 'picked_up'
        ");
        $received->execute([$userId]);

        $avgGiven = $pdo->prepare("
            SELECT AVG(rating) AS avg_rating FROM requests
            WHERE consumer_id = ? AND rating IS NOT NULL
        ");
        $avgGiven->execute([$userId]);

        $avgReceived = $pdo->prepare("
            SELECT AVG(r.rating) AS avg_received FROM requests r
            JOIN ads a ON r.ad_id = a.id
            WHERE a.cook_id = ? AND r.rating IS NOT NULL
        ");
        $avgReceived->execute([$userId]);

        $g  = $given->fetch();
        $r  = $received->fetch();
        $ag = $avgGiven->fetch();
        $ar = $avgReceived->fetch();

        jsonResponse([
            'user_stats' => [
                'given'               => intval($g['given']),
                'received'            => intval($r['received']),
                'avg_rating_given'    => $ag['avg_rating']   ? round($ag['avg_rating'],   1) : null,
                'avg_rating_received' => $ar['avg_received'] ? round($ar['avg_received'], 1) : null,
            ],
        ]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}
