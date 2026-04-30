<?php
/**
 * UniBite - Ads API
 * Διαχείριση Αγγελιών Φαγητού
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

/**
 * Λήψη ενεργών αγγελιών (Β1: Τελευταίες 48 ώρες)
 */
function getActiveAds() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.username as cook_name,
                CASE 
                    WHEN a.available_portions > 0 THEN 'Active' 
                    ELSE 'Inactive' 
                END as current_state
            FROM ads a
            JOIN users u ON a.cook_id = u.id
            WHERE a.created_at >= NOW() - INTERVAL 48 HOUR
            ORDER BY a.created_at DESC
        ");
        $stmt->execute();
        $ads = $stmt->fetchAll();
        
        jsonResponse(['ads' => $ads, 'count' => count($ads)]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Λήψη αγγελιών του τρέχοντος χρήστη
 */
function getMyAds() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM ads 
            WHERE cook_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $ads = $stmt->fetchAll();
        
        jsonResponse(['ads' => $ads]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Λήψη αγγελίας по ID
 */
function getAdById() {
    global $pdo;
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        jsonResponse(['error' => 'Απαιτείται ID αγγελίας'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.username as cook_name
            FROM ads a
            JOIN users u ON a.cook_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $ad = $stmt->fetch();
        
        if (!$ad) {
            jsonResponse(['error' => 'Αγγελία δεν βρέθηκε'], 404);
        }
        
        jsonResponse(['ad' => $ad]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Δημιουργία νέας αγγελίας
 */
function createAd() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Υποχρεωτικά πεδία
    $title = trim($input['title'] ?? '');
    $total_portions = intval($input['total_portions'] ?? 0);
    $pickup_location = trim($input['pickup_location'] ?? '');
    $pickup_time = $input['pickup_time'] ?? '';
    
    if (empty($title) || $total_portions <= 0 || empty($pickup_location) || empty($pickup_time)) {
        jsonResponse(['error' => 'Υποχρεωτικά πεδία: title, total_portions, pickup_location, pickup_time'], 400);
    }
    
    $credit_costs = intval($input['credit_costs'] ?? 1);
    $description = trim($input['description'] ?? '');
    $allergens = trim($input['allergens'] ?? '');
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ads (
                cook_id, title, credit_costs, description, allergens,
                total_portions, available_portions, pickup_location, pickup_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $title,
            $credit_costs,
            $description,
            $allergens,
            $total_portions,
            $total_portions,
            $pickup_location,
            $pickup_time
        ]);
        
        $adId = $pdo->lastInsertId();
        
        jsonResponse([
            'message' => 'Αγγελία δημιουργήθηκε επιτυχώς!',
            'ad' => ['id' => $adId, 'title' => $title]
        ], 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Ενημέρωση αγγελίας
 */
function updateAd() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    
    if (!$id) {
        jsonResponse(['error' => 'Απαιτείται ID αγγελίας'], 400);
    }
    
    // Έλεγχος ιδιοκτησίας
    try {
        $stmt = $pdo->prepare("SELECT cook_id FROM ads WHERE id = ?");
        $stmt->execute([$id]);
        $ad = $stmt->fetch();
        
        if (!$ad) {
            jsonResponse(['error' => 'Αγγελία δεν βρέθηκε'], 404);
        }
        
        if ($ad['cook_id'] != $_SESSION['user_id']) {
            jsonResponse(['error' => 'Δεν έχετε δικαίωμα επεξεργασίας'], 403);
        }
        
        // Ενημέρωση
        $fields = [];
        $params = [];
        
        if (isset($input['title'])) {
            $fields[] = 'title = ?';
            $params[] = $input['title'];
        }
        if (isset($input['description'])) {
            $fields[] = 'description = ?';
            $params[] = $input['description'];
        }
        if (isset($input['credit_costs'])) {
            $fields[] = 'credit_costs = ?';
            $params[] = $input['credit_costs'];
        }
        if (isset($input['available_portions'])) {
            $fields[] = 'available_portions = ?';
            $params[] = $input['available_portions'];
        }
        
        if (empty($fields)) {
            jsonResponse(['error' => 'Δεν δόθηκαν πεδία για ενημέρωση'], 400);
        }
        
        $params[] = $id;
        
        $sql = "UPDATE ads SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse(['message' => 'Αγγελία ενημερώθηκε επιτυχώς']);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Διαγραφή αγγελίας
 */
function deleteAd() {
    global $pdo;
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        jsonResponse(['error' => 'Απαιτείται ID αγγελίας'], 400);
    }
    
    try {
        // Έλεγχος ιδιοκτησίας
        $stmt = $pdo->prepare("SELECT cook_id FROM ads WHERE id = ?");
        $stmt->execute([$id]);
        $ad = $stmt->fetch();
        
        if (!$ad) {
            jsonResponse(['error' => 'Αγγελία δεν βρέθηκε'], 404);
        }
        
        if ($ad['cook_id'] != $_SESSION['user_id']) {
            jsonResponse(['error' => 'Δεν έχετε δικαίωμα διαγραφής'], 403);
        }
        
        // Διαγραφή (CASCADE θα διαγράψει και τα requests)
        $stmt = $pdo->prepare("DELETE FROM ads WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse(['message' => 'Αγγελία διαγράφηκε επιτυχώς']);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}