<?php
/**
 * UniBite - Requests API
 * Διαχείριση Αιτημάτων & Συναλλαγών
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
    default:
        jsonResponse(['error' => 'Μη επιτρεπόμενη μέθοδος'], 405);
}

function handleGet() {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'my-requests':
            requireAuth();
            getMyRequests();
            break;
        case 'incoming':
            requireAuth();
            getIncomingRequests();
            break;
        case 'history':
            requireAuth();
            getRequestHistory();
            break;
        case 'cook-history':
            requireAuth();
            getCookHistory();
            break;
        default:
            jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
    }
}

function handlePost() {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'create') {
        createRequest();
    } else {
        jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
    }
}

function handlePut() {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'approve') {
        approveRequest();
    } elseif ($action === 'reject') {
        rejectRequest();
    } elseif ($action === 'rate') {
        rateRequest();
    } elseif ($action === 'pickup') {
        markAsPickedUp();
    } else {
        jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
    }
}

/**
 * Λήψη αιτημάτων του τρέχοντος χρήστη (ως καταναλωτής)
 */
function getMyRequests() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.title, a.pickup_location, a.pickup_time, a.credit_costs,
                   u.username as cook_name
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            JOIN users u ON a.cook_id = u.id
            WHERE r.consumer_id = ?
            ORDER BY r.id DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $requests = $stmt->fetchAll();
        
        jsonResponse(['requests' => $requests]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Λήψη εισερχόμενων αιτημάτων (για τον μάγειρα)
 */
function getIncomingRequests() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.title, a.pickup_location, a.pickup_time, a.credit_costs,
                   u.username as consumer_name
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            JOIN users u ON r.consumer_id = u.id
            WHERE a.cook_id = ?
            ORDER BY r.id DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $requests = $stmt->fetchAll();
        
        jsonResponse(['requests' => $requests]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Ιστορικό ολοκληρωμένων αιτημάτων
 */
function getRequestHistory() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.title
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            WHERE r.consumer_id = ? AND r.status IN ('picked_up', 'no_show')
            ORDER BY r.id DESC
            LIMIT 20
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $requests = $stmt->fetchAll();
        
        jsonResponse(['history' => $requests]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Δημιουργία νέου αιτήματος (παραγγελία)
 */
function createRequest() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $ad_id = intval($input['ad_id'] ?? 0);
    $quantity = intval($input['quantity'] ?? 1);
    
    if (!$ad_id || $quantity < 1) {
        jsonResponse(['error' => 'Απαιτείται ad_id και quantity'], 400);
    }
    
    try {
        // Έλεγχος αγγελίας
        $stmt = $pdo->prepare("
            SELECT a.*, u.username as cook_name
            FROM ads a
            JOIN users u ON a.cook_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$ad_id]);
        $ad = $stmt->fetch();
        
        if (!$ad) {
            jsonResponse(['error' => 'Αγγελία δεν βρέθηκε'], 404);
        }
        
        // Έλεγχος διαθεσιμότητας
        if ($ad['available_portions'] < $quantity) {
            jsonResponse(['error' => 'Δεν υπάρχουν αρκετές μερίδες'], 400);
        }
        
        // Έλεγχος credits
        $totalCost = $ad['credit_costs'] * $quantity;
        
        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user['credits'] < $totalCost) {
            jsonResponse(['error' => 'Δεν έχετε αρκετά Credits'], 400);
        }
        
        // === START TRANSACTION ===
        $pdo->beginTransaction();
        
        try {
            // Αφαίρεση credits από καταναλωτή
            $stmt = $pdo->prepare("
                UPDATE users 
                SET credits = credits - ?
                WHERE id = ?
            ");
            $stmt->execute([$totalCost, $_SESSION['user_id']]);
            
            // Μείωση διαθέσιμων μερίδων
            $stmt = $pdo->prepare("
                UPDATE ads 
                SET available_portions = available_portions - ?
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $ad_id]);
            
            // Δημιουργία αιτήματος
            $stmt = $pdo->prepare("
                INSERT INTO requests (ad_id, consumer_id, quantity, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$ad_id, $_SESSION['user_id'], $quantity]);
            
            $pdo->commit();
            
            // Ενημέρωση session credits
            $_SESSION['credits'] -= $totalCost;
            
            jsonResponse([
                'message' => 'Παραγγελία επιτυχής!',
                'transaction' => [
                    'ad_id' => $ad_id,
                    'quantity' => $quantity,
                    'total_cost' => $totalCost,
                    'status' => 'pending'
                ]
            ], 201);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Έγκριση αιτήματος (από μάγειρα)
 */
function approveRequest() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($input['request_id'] ?? 0);

    if (!$request_id) {
        jsonResponse(['error' => 'Απαιτείται request_id'], 400);
    }

    try {
        // Έλεγχος ότι ο μάγειρας είναι ο ιδιοκτήτης της αγγελίας
        $stmt = $pdo->prepare("
            SELECT r.*, a.cook_id 
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            WHERE r.id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            jsonResponse(['error' => 'Αίτημα δεν βρέθηκε'], 404);
        }
        
        if ($request['cook_id'] != $_SESSION['user_id']) {
            jsonResponse(['error' => 'Δεν έχετε δικαίωμα έγκρισης'], 403);
        }
        
        if ($request['status'] !== 'pending') {
            jsonResponse(['error' => 'Το αίτημα δεν είναι σε εκκρεμή κατάσταση'], 400);
        }
        
        // Έγκριση
        $stmt = $pdo->prepare("UPDATE requests SET status = 'approved' WHERE id = ?");
        $stmt->execute([$request_id]);
        
        jsonResponse(['message' => 'Αίτημα εγκρίθηκε']);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Απόρριψη αιτήματος
 */
function rejectRequest() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($input['request_id'] ?? 0);
    
    if (!$request_id) {
        jsonResponse(['error' => 'Απαιτείται request_id'], 400);
    }
    
    try {
        // Έλεγχος ιδιοκτησίας
        $stmt = $pdo->prepare("
            SELECT r.*, a.cook_id, a.credit_costs, r.quantity
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            WHERE r.id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            jsonResponse(['error' => 'Αίτημα δεν βρέθηκε'], 404);
        }
        
        if ($request['cook_id'] != $_SESSION['user_id']) {
            jsonResponse(['error' => 'Δεν έχετε δικαίωμα απόρριψης'], 403);
        }
        
        // === START TRANSACTION ===
        $pdo->beginTransaction();
        
        try {
            // Επιστροφή credits στον καταναλωτή
            $refund = $request['credit_costs'] * $request['quantity'];
            $stmt = $pdo->prepare("
                UPDATE users 
                SET credits = credits + ?
                WHERE id = ?
            ");
            $stmt->execute([$refund, $request['consumer_id']]);
            
            // Επιστροφή μερίδων
            $stmt = $pdo->prepare("
                UPDATE ads 
                SET available_portions = available_portions + ?
                WHERE id = ?
            ");
            $stmt->execute([$request['quantity'], $request['ad_id']]);
            
            // Απόρριψη αιτήματος
            $stmt = $pdo->prepare("UPDATE requests SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$request_id]);
            
            $pdo->commit();
            
            jsonResponse(['message' => 'Αίτημα απορρίφθηκε', 'refund' => $refund]);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Σήμανση ως παραληφθέν & Βαθμολογία (Β4)
 */
function rateRequest() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($input['request_id'] ?? 0);
    $rating = intval($input['rating'] ?? 0);
    
    if (!$request_id || $rating < 1 || $rating > 5) {
        jsonResponse(['error' => 'Απαιτείται request_id και rating (1-5)'], 400);
    }
    
    try {
        // Έλεγχος αιτήματος
        $stmt = $pdo->prepare("
            SELECT r.*, a.cook_id, a.credit_costs, r.quantity
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            WHERE r.id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            jsonResponse(['error' => 'Αίτημα δεν βρέθηκε'], 404);
        }
        
        if ($request['consumer_id'] != $_SESSION['user_id']) {
            jsonResponse(['error' => 'Δεν έχετε δικαίωμα βαθμολογίας'], 403);
        }
        
        if ($request['status'] !== 'approved') {
            jsonResponse(['error' => 'Το αίτημα πρέπει να είναι εγκεκριμένο'], 400);
        }
        
        // === START TRANSACTION ===
        $pdo->beginTransaction();
        
        try {
            // Υπολογισμός ανταμοιβής μάγειρα (Β4)
            // Αν rating > 3 → +2 credits/μερίδα
            // Αν rating ≤ 3 → +1 credit/μερίδα
            $reward = $rating > 3 
                ? ($request['credit_costs'] + 1) * $request['quantity']
                : $request['credit_costs'] * $request['quantity'];
            
            // Πληρωμή μάγειρα
            $stmt = $pdo->prepare("
                UPDATE users 
                SET credits = credits + ?
                WHERE id = ?
            ");
            $stmt->execute([$reward, $request['cook_id']]);
            
            // Ενημέρωση αιτήματος
            $stmt = $pdo->prepare("
                UPDATE requests 
                SET status = 'picked_up', rating = ?, received_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$rating, $request_id]);
            
            $pdo->commit();
            
            jsonResponse([
                'message' => 'Ευχαριστούμε για τη βαθμολογία!',
                'reward' => $reward,
                'rating' => $rating
            ]);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

/**
 * Σήμανση ως παραληφθέν χωρίς rating
 */
function getCookHistory() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.title, a.credit_costs, u.username as consumer_name
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            JOIN users u ON r.consumer_id = u.id
            WHERE a.cook_id = ? AND r.status IN ('picked_up', 'rejected', 'no_show')
            ORDER BY r.id DESC
            LIMIT 30
        ");
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse(['history' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

function markAsPickedUp() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($input['request_id'] ?? 0);
    
    if (!$request_id) {
        jsonResponse(['error' => 'Απαιτείται request_id'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE requests SET status = 'picked_up' WHERE id = ?");
        $stmt->execute([$request_id]);
        
        jsonResponse(['message' => 'Σημειώθηκε ως παραληφθέν']);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}