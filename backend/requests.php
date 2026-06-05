<?php
/**
 * UniBite - Requests API
 * Endpoints: my-requests, incoming, history, cook-history,
 *            create, approve, reject, rate
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
            requireAuth(); getMyRequests(); break;
        case 'incoming':
            requireAuth(); getIncomingRequests(); break;
        case 'history':
            requireAuth(); getConsumerHistory(); break;
        case 'cook-history':
            requireAuth(); getCookHistory(); break;
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
    switch ($action) {
        case 'approve': approveRequest(); break;
        case 'reject':  rejectRequest();  break;
        case 'rate':    rateRequest();    break;
        default:        jsonResponse(['error' => 'Άγνωστη ενέργεια'], 400);
    }
}

// Παραγγελίες του καταναλωτή
function getMyRequests() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.title, a.pickup_location, a.pickup_time, a.credit_costs,
                   u.username AS cook_name
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            JOIN users u ON a.cook_id = u.id
            WHERE r.consumer_id = ?
            ORDER BY r.id DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse(['requests' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Εισερχόμενες παραγγελίες για τον μάγειρα
function getIncomingRequests() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.title, a.pickup_location, a.pickup_time, a.credit_costs,
                   u.username AS consumer_name
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            JOIN users u ON r.consumer_id = u.id
            WHERE a.cook_id = ?
            ORDER BY r.id DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse(['requests' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Ιστορικό ολοκληρωμένων για καταναλωτή
function getConsumerHistory() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.title, u.username AS cook_name
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            JOIN users u ON a.cook_id = u.id
            WHERE r.consumer_id = ? AND r.status IN ('picked_up','rejected','no_show')
            ORDER BY r.id DESC
            LIMIT 30
        ");
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse(['history' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Ιστορικό ολοκληρωμένων για μάγειρα
function getCookHistory() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.title, a.credit_costs, u.username AS consumer_name
            FROM requests r
            JOIN ads a ON r.ad_id = a.id
            JOIN users u ON r.consumer_id = u.id
            WHERE a.cook_id = ? AND r.status IN ('picked_up','rejected','no_show')
            ORDER BY r.id DESC
            LIMIT 30
        ");
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse(['history' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Δημιουργία παραγγελίας με transaction
function createRequest() {
    global $pdo;
    $input    = json_decode(file_get_contents('php://input'), true);
    $ad_id    = intval($input['ad_id']    ?? 0);
    $quantity = intval($input['quantity'] ?? 1);

    if (!$ad_id || $quantity < 1) {
        jsonResponse(['error' => 'Απαιτείται ad_id και quantity'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.username AS cook_name
            FROM ads a JOIN users u ON a.cook_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$ad_id]);
        $ad = $stmt->fetch();
        if (!$ad) jsonResponse(['error' => 'Αγγελία δεν βρέθηκε'], 404);

        if ($ad['available_portions'] < $quantity) {
            jsonResponse(['error' => 'Δεν υπάρχουν αρκετές μερίδες'], 400);
        }

        $totalCost = $ad['credit_costs'] * $quantity;

        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user['credits'] < $totalCost) {
            jsonResponse(['error' => 'Δεν έχετε αρκετά Credits'], 400);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ?")
                ->execute([$totalCost, $_SESSION['user_id']]);

            $pdo->prepare("UPDATE ads SET available_portions = available_portions - ? WHERE id = ?")
                ->execute([$quantity, $ad_id]);

            $pdo->prepare("INSERT INTO requests (ad_id, consumer_id, quantity, status) VALUES (?, ?, ?, 'pending')")
                ->execute([$ad_id, $_SESSION['user_id'], $quantity]);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }

        $_SESSION['credits'] -= $totalCost;

        jsonResponse([
            'message'     => 'Παραγγελία επιτυχής!',
            'transaction' => [
                'ad_id'      => $ad_id,
                'quantity'   => $quantity,
                'total_cost' => $totalCost,
                'status'     => 'pending',
            ],
        ], 201);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Έγκριση παραγγελίας από μάγειρα
function approveRequest() {
    global $pdo;
    $input      = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($input['request_id'] ?? 0);
    if (!$request_id) jsonResponse(['error' => 'Απαιτείται request_id'], 400);

    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.cook_id FROM requests r
            JOIN ads a ON r.ad_id = a.id WHERE r.id = ?
        ");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch();
        if (!$req) jsonResponse(['error' => 'Αίτημα δεν βρέθηκε'], 404);
        if ($req['cook_id'] != $_SESSION['user_id']) jsonResponse(['error' => 'Δεν έχετε δικαίωμα'], 403);
        if ($req['status'] !== 'pending') jsonResponse(['error' => 'Το αίτημα δεν είναι σε εκκρεμή κατάσταση'], 400);

        $pdo->prepare("UPDATE requests SET status = 'approved' WHERE id = ?")->execute([$request_id]);
        jsonResponse(['message' => 'Αίτημα εγκρίθηκε']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Απόρριψη παραγγελίας + επιστροφή credits
function rejectRequest() {
    global $pdo;
    $input      = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($input['request_id'] ?? 0);
    if (!$request_id) jsonResponse(['error' => 'Απαιτείται request_id'], 400);

    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.cook_id, a.credit_costs FROM requests r
            JOIN ads a ON r.ad_id = a.id WHERE r.id = ?
        ");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch();
        if (!$req) jsonResponse(['error' => 'Αίτημα δεν βρέθηκε'], 404);
        if ($req['cook_id'] != $_SESSION['user_id']) jsonResponse(['error' => 'Δεν έχετε δικαίωμα'], 403);

        $refund = $req['credit_costs'] * $req['quantity'];

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")
                ->execute([$refund, $req['consumer_id']]);

            $pdo->prepare("UPDATE ads SET available_portions = available_portions + ? WHERE id = ?")
                ->execute([$req['quantity'], $req['ad_id']]);

            $pdo->prepare("UPDATE requests SET status = 'rejected' WHERE id = ?")
                ->execute([$request_id]);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }

        jsonResponse(['message' => 'Αίτημα απορρίφθηκε', 'refund' => $refund]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

// Βαθμολογία + πληρωμή μάγειρα
function rateRequest() {
    global $pdo;
    $input      = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($input['request_id'] ?? 0);
    $rating     = intval($input['rating']     ?? 0);

    if (!$request_id || $rating < 1 || $rating > 5) {
        jsonResponse(['error' => 'Απαιτείται request_id και rating (1-5)'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.cook_id, a.credit_costs FROM requests r
            JOIN ads a ON r.ad_id = a.id WHERE r.id = ?
        ");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch();
        if (!$req) jsonResponse(['error' => 'Αίτημα δεν βρέθηκε'], 404);
        if ($req['consumer_id'] != $_SESSION['user_id']) jsonResponse(['error' => 'Δεν έχετε δικαίωμα'], 403);
        if ($req['status'] !== 'approved') jsonResponse(['error' => 'Το αίτημα πρέπει να είναι εγκεκριμένο'], 400);

        // Rating > 3 → bonus credit για τον μάγειρα
        $reward = $rating > 3
            ? ($req['credit_costs'] + 1) * $req['quantity']
            : $req['credit_costs'] * $req['quantity'];

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")
                ->execute([$reward, $req['cook_id']]);

            $pdo->prepare("UPDATE requests SET status = 'picked_up', rating = ?, received_at = NOW() WHERE id = ?")
                ->execute([$rating, $request_id]);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }

        jsonResponse(['message' => 'Ευχαριστούμε για τη βαθμολογία!', 'reward' => $reward, 'rating' => $rating]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}
