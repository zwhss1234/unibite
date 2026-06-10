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

// Ενεργές/ανενεργές αγγελίες των τελευταίων 48 ωρών
function getActiveAds() {
    global $pdo;

    // Προαιρετικό φίλτρο απόστασης
    $userLat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
    $userLng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
    $maxKm   = isset($_GET['km'])  ? floatval($_GET['km'])  : null;

    try {
        $sql = "
            SELECT a.*, u.username AS cook_name,
                CASE WHEN a.available_portions > 0 THEN 'Active' ELSE 'Inactive' END AS current_state
            FROM ads a
            JOIN users u ON a.cook_id = u.id
            WHERE a.created_at >= NOW() - INTERVAL 48 HOUR
            ORDER BY a.created_at DESC
        ";
        $ads = $pdo->query($sql)->fetchAll();

        // Εφαρμογή φίλτρου απόστασης (Haversine) αν δόθηκαν συντεταγμένες χρήστη
        if ($userLat !== null && $userLng !== null) {
            foreach ($ads as &$ad) {
                if ($ad['latitude'] && $ad['longitude']) {
                    $ad['distance_km'] = haversine($userLat, $userLng, floatval($ad['latitude']), floatval($ad['longitude']));
                } else {
                    $ad['distance_km'] = null;
                }
            }
            unset($ad);

            // Φιλτράρισμα βάσει max km αν ζητήθηκε
            if ($maxKm !== null) {
                $ads = array_filter($ads, fn($a) => $a['distance_km'] === null || $a['distance_km'] <= $maxKm);
                $ads = array_values($ads);
            }

            // Ταξινόμηση: με συντεταγμένες πρώτα, κοντινότερες πρώτα
            usort($ads, function($a, $b) {
                if ($a['distance_km'] === null && $b['distance_km'] === null) return 0;
                if ($a['distance_km'] === null) return 1;
                if ($b['distance_km'] === null) return -1;
                return $a['distance_km'] <=> $b['distance_km'];
            });
        }

        jsonResponse(['ads' => $ads, 'count' => count($ads)]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Σφάλμα βάσης δεδομένων'], 500);
    }
}

function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)*sin($dLon/2);
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
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

// Δημιουργία νέας αγγελίας (δέχεται multipart/form-data λόγω upload εικόνας)
function createAd() {
    global $pdo;

    $title           = trim($_POST['title']           ?? '');
    $total_portions  = intval($_POST['total_portions'] ?? 0);
    $pickup_location = trim($_POST['pickup_location'] ?? '');
    $pickup_time     = $_POST['pickup_time'] ?? '';

    if (empty($title) || $total_portions <= 0 || empty($pickup_location) || empty($pickup_time)) {
        jsonResponse(['error' => 'Υποχρεωτικά πεδία: title, total_portions, pickup_location, pickup_time'], 400);
    }

    $credit_costs = intval($_POST['credit_costs'] ?? 1);
    $description  = trim($_POST['description']   ?? '');
    $allergens    = trim($_POST['allergens']      ?? '');
    $latitude     = isset($_POST['latitude'])  && $_POST['latitude']  !== '' ? floatval($_POST['latitude'])  : null;
    $longitude    = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : null;

    // Upload εικόνας (προαιρετικά)
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed_mime)) {
            jsonResponse(['error' => 'Επιτρέπονται μόνο εικόνες (jpg, png, gif, webp)'], 400);
        }

        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            jsonResponse(['error' => 'Μέγιστο μέγεθος εικόνας: 5MB'], 400);
        }

        $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $filename = 'food_' . uniqid() . '.' . $ext;
        $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
            jsonResponse(['error' => 'Αποτυχία αποθήκευσης εικόνας'], 500);
        }

        $image_path = 'uploads/' . $filename;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO ads (cook_id, title, credit_costs, description, allergens,
                             total_portions, available_portions, pickup_location, pickup_time,
                             image_path, latitude, longitude)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $title, $credit_costs, $description, $allergens,
            $total_portions, $total_portions,
            $pickup_location, $pickup_time,
            $image_path, $latitude, $longitude,
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
