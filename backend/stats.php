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
    case 'leaderboard':   getLeaderboard();                      break;
    case 'stats':         getStats();                            break;
    case 'user-stats':    requireAuth(); getUserStats();         break;
    case 'admin-stats':   requireAuth(); getAdminStats();        break;
    default:              jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
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

// Admin dashboard — πρόσβαση μόνο για admin
function getAdminStats() {
    global $pdo;
    if (($_SESSION['role'] ?? '') !== 'admin') {
        jsonResponse(['error' => 'Απαιτείται πρόσβαση διαχειριστή'], 403);
    }
    try {
        // Δ1: Μερίδες τελευταίου μήνα
        $portions = $pdo->query("
            SELECT COALESCE(SUM(r.quantity), 0) AS total
            FROM requests r
            WHERE r.status = 'picked_up' AND r.received_at >= NOW() - INTERVAL 1 MONTH
        ")->fetch();

        // Συνολικοί χρήστες (εκτός admin)
        $users = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE role != 'admin'")->fetch();

        // Ενεργές αγγελίες τώρα
        $activeAds = $pdo->query("
            SELECT COUNT(*) AS total FROM ads
            WHERE created_at >= NOW() - INTERVAL 48 HOUR AND available_portions > 0
        ")->fetch();

        // Δ2: Top Donor (μερίδες που δόθηκαν)
        $topDonor = $pdo->query("
            SELECT u.username, COALESCE(SUM(r.quantity), 0) AS portions_given
            FROM users u
            JOIN ads a ON u.id = a.cook_id
            JOIN requests r ON a.id = r.ad_id
            WHERE r.status = 'picked_up'
            GROUP BY u.id, u.username
            ORDER BY portions_given DESC
            LIMIT 1
        ")->fetch();

        // Δ2: Top 5 γεύματα με υψηλότερη αξιολόγηση
        $topRated = $pdo->query("
            SELECT a.title, u.username AS cook_name,
                   ROUND(AVG(r.rating), 1) AS avg_rating,
                   COUNT(r.id) AS review_count
            FROM ads a
            JOIN users u ON a.cook_id = u.id
            JOIN requests r ON a.id = r.ad_id
            WHERE r.rating IS NOT NULL
            GROUP BY a.id, a.title, u.username
            ORDER BY avg_rating DESC, review_count DESC
            LIMIT 5
        ")->fetchAll();

        // Μηνιαίο ιστορικό (τελευταίοι 6 μήνες)
        $monthly = $pdo->query("
            SELECT DATE_FORMAT(received_at, '%Y-%m') AS month,
                   COALESCE(SUM(quantity), 0)        AS portions
            FROM requests
            WHERE status = 'picked_up' AND received_at >= NOW() - INTERVAL 6 MONTH
            GROUP BY month
            ORDER BY month ASC
        ")->fetchAll();

        jsonResponse([
            'portions_last_month' => intval($portions['total']),
            'total_users'         => intval($users['total']),
            'active_ads'          => intval($activeAds['total']),
            'top_donor'           => $topDonor ?: null,
            'top_rated_meals'     => $topRated,
            'monthly_portions'    => $monthly,
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
