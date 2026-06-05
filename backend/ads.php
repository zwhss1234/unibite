<?php
/**
 * UniBite - Ads API
 * Endpoints: feed, my-ads, view, create, update, delete
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        requireAuth();
        handlePost();
        break;
    case 'PUT':
    case 'PATCH':
        requireAuth();
        handlePut();
        break;
    case 'DELETE':
        requireAuth();
        handleDelete();
        break;
    default:
        jsonResponse(['error' => 'Μη επιτρεπόμενη μέθοδος'], 405);
}

function handleGet() {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'feed':
            getActiveAds();
            break;
        case 'my-ads':
            requireAuth();
            getMyAds();
            break;
        case 'view':
            getAdById();
            break;
        default:
            jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
    }
}

function handlePost() {
    $action = $_GET['action'] ?? '';
    if ($action === 'create') {
        createAd();
    } else {
        jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
    }
}

function handlePut() {
    updateAd();
}

function handleDelete() {
    deleteAd();
}

// Ενεργές αγγελίες των τελευταίων 48 ωρών
function getActiveAds() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT a.*, u.username AS cook_name,
                CASE WHEN a.available_portions > 0 THEN 'Active' ELSE 'Inactive' END AS current_state
            FROM ads a
            JOIN users u ON a.cook_id = u.id
            WHERE a.created_at >= NOW() - INTERVAL 48 HOUR
            ORDER BY a.created_at DESC
        ");
        $ads = $stmt->fetchAll();
        jsonResponse(['ads' => $ads, 'count' => count($ads)]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Αγγελίες του τρέχοντος μάγειρα
function getMyAds() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM ads WHERE cook_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse(['ads' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Μία αγγελία με βάση ID
function getAdById() {
    global $pdo;
    $id = intval($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Απαιτείται ID'], 400);

    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.username AS cook_name
            FROM ads a JOIN users u ON a.cook_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $ad = $stmt->fetch();
        if (!$ad) jsonResponse(['error' => 'Αγγελία δεν βρέθηκε'], 404);
        jsonResponse(['ad' => $ad]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Δημιουργία νέας αγγελίας
function createAd() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $title           = trim($input['title']           ?? '');
    $total_portions  = intval($input['total_portions'] ?? 0);
    $pickup_location = trim($input['pickup_location'] ?? '');
    $pickup_time     = $input['pickup_time'] ?? '';

    if (empty($title) || $total_portions <= 0 || empty($pickup_location) || empty($pickup_time)) {
        jsonResponse(['error' => 'Υποχρεωτικά πεδία: title, total_portions, pickup_location, pickup_time'], 400);
    }

    $credit_costs = intval($input['credit_costs'] ?? 1);
    $description  = trim($input['description']   ?? '');
    $allergens    = trim($input['allergens']      ?? '');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO ads (cook_id, title, credit_costs, description, allergens,
                             total_portions, available_portions, pickup_location, pickup_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $title, $credit_costs, $description, $allergens,
            $total_portions, $total_portions,
            $pickup_location, $pickup_time,
        ]);
        $adId = $pdo->lastInsertId();
        jsonResponse(['message' => 'Αγγελία δημιουργήθηκε!', 'ad' => ['id' => $adId, 'title' => $title]], 201);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Ενημέρωση αγγελίας
function updateAd() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $id    = intval($input['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Απαιτείται ID'], 400);

    try {
        $stmt = $pdo->prepare("SELECT cook_id FROM ads WHERE id = ?");
        $stmt->execute([$id]);
        $ad = $stmt->fetch();
        if (!$ad) jsonResponse(['error' => 'Αγγελία δεν βρέθηκε'], 404);
        if ($ad['cook_id'] != $_SESSION['user_id']) jsonResponse(['error' => 'Δεν έχετε δικαίωμα'], 403);

        $fields = [];
        $params = [];

        $allowed = ['title', 'description', 'credit_costs', 'available_portions', 'allergens'];
        foreach ($allowed as $field) {
            if (isset($input[$field])) {
                $fields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }

        if (empty($fields)) jsonResponse(['error' => 'Δεν δόθηκαν πεδία'], 400);

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE ads SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($params);
        jsonResponse(['message' => 'Αγγελία ενημερώθηκε']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Διαγραφή αγγελίας
function deleteAd() {
    global $pdo;
    $id = intval($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Απαιτείται ID'], 400);

    try {
        $stmt = $pdo->prepare("SELECT cook_id FROM ads WHERE id = ?");
        $stmt->execute([$id]);
        $ad = $stmt->fetch();
        if (!$ad) jsonResponse(['error' => 'Αγγελία δεν βρέθηκε'], 404);
        if ($ad['cook_id'] != $_SESSION['user_id']) jsonResponse(['error' => 'Δεν έχετε δικαίωμα'], 403);

        $stmt = $pdo->prepare("DELETE FROM ads WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['message' => 'Αγγελία διαγράφηκε']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}
