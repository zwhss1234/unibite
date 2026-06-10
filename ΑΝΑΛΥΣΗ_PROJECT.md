# Τεχνική Ανάλυση — UniBite

## Σύντομη Περιγραφή

Το **UniBite** είναι μια **Single Page Application (SPA)** που επιτρέπει σε φοιτητές να μοιράζονται σπιτικό φαγητό μέσω ενός συστήματος εικονικών credits. Οι μάγειρες δημοσιεύουν αγγελίες και οι καταναλωτές παραγγέλνουν πληρώνοντας με credits.

**Stack:** PHP 8 · MariaDB · Vanilla JavaScript · HTML5 · CSS3 · Leaflet.js  
**Server:** Apache (XAMPP) · χωρίς framework σε backend ή frontend

---

## 1. Αρχιτεκτονική

```
Browser (SPA)
    │
    │  HTTP (JSON)
    ▼
Apache / PHP Backend
    │
    │  PDO
    ▼
MariaDB (unibite_db)
```

Όλο το UI βρίσκεται σε **ένα αρχείο** (`index.html`). Η JavaScript αλλάζει ποιο section είναι ορατό χωρίς να φορτώνει νέες σελίδες. Το backend είναι 5 ξεχωριστά PHP αρχεία, το καθένα ως ανεξάρτητο REST endpoint.

---

## 2. Βάση Δεδομένων

### Πίνακες

#### `users`
| Πεδίο | Τύπος | Περιγραφή |
|---|---|---|
| id | INT PK | Auto increment |
| username | VARCHAR(50) | Μοναδικό όνομα χρήστη |
| email | VARCHAR(100) UNIQUE | Χρησιμοποιείται για σύνδεση (χωρίς password) |
| role | ENUM | `cook` / `consumer` / `admin` |
| credits | INT DEFAULT 5 | Εικονικό νόμισμα — ξεκινά από 5 |
| avatar_path | VARCHAR(255) NULL | Σχετική διαδρομή εικόνας προφίλ π.χ. `uploads/avatars/avatar_1_xxx.jpg` |
| created_at | TIMESTAMP | Ημερομηνία εγγραφής |

#### `ads`
| Πεδίο | Τύπος | Περιγραφή |
|---|---|---|
| id | INT PK | Auto increment |
| cook_id | INT FK → users.id | Ποιος μάγειρας δημοσίευσε |
| title | VARCHAR(100) | Τίτλος φαγητού |
| credit_costs | INT | Κόστος ανά μερίδα σε credits |
| description | TEXT | Προαιρετική περιγραφή |
| image_path | VARCHAR(255) NULL | Σχετική διαδρομή εικόνας π.χ. `uploads/foto_xxx.jpg` |
| total_portions | INT | Συνολικές μερίδες |
| available_portions | INT | Διαθέσιμες αυτή τη στιγμή |
| allergens | TEXT | Αλλεργιογόνα (ελεύθερο κείμενο) |
| pickup_location | VARCHAR(255) | Τοποθεσία παραλαβής |
| pickup_time | VARCHAR(100) | Ώρα παραλαβής |
| latitude | DECIMAL(9,6) NULL | Γεωγραφικό πλάτος (GPS) — για χάρτη & απόσταση |
| longitude | DECIMAL(9,6) NULL | Γεωγραφικό μήκος (GPS) — για χάρτη & απόσταση |
| created_at | TIMESTAMP | Χρησιμοποιείται για το 48ω φίλτρο |

#### `requests`
| Πεδίο | Τύπος | Περιγραφή |
|---|---|---|
| id | INT PK | Auto increment |
| ad_id | INT FK → ads.id | Ποια αγγελία αφορά |
| consumer_id | INT FK → users.id | Ποιος παρήγγειλε |
| quantity | INT | Πόσες μερίδες |
| status | ENUM | `pending` / `approved` / `rejected` / `picked_up` / `no_show` |
| rating | INT (1-5) | Βαθμολογία — NULL μέχρι picked_up |
| received_at | TIMESTAMP | Πότε παραλήφθηκε |
| created_at | TIMESTAMP | Πότε έγινε η παραγγελία |

### Views

**`active_ads`** — αγγελίες των τελευταίων 48 ωρών με το username του μάγειρα και κατάσταση `Active` (available_portions > 0) ή `Inactive` (= 0).

**`leaderboard`** — μάγειρες ταξινομημένοι κατά αριθμό παραγγελιών που ολοκλήρωσαν (`picked_up`).

### Stored Procedures

**`create_request(ad_id, consumer_id, quantity)`**  
Εκτελεί σε transaction: αφαιρεί credits από καταναλωτή, μειώνει available_portions, δημιουργεί request.

**`rate_and_pay(request_id, rating)`**  
Εκτελεί σε transaction: υπολογίζει reward (με ή χωρίς bonus), πληρώνει μάγειρα, ενημερώνει status σε `picked_up`.

**`handle_no_show(consumer_id, ad_id)`**  
Εκτελεί σε transaction: αφαιρεί 1 credit ποινή, status → `no_show`, επιστρέφει μερίδα στην αγγελία.

### Indexes
```sql
idx_ads_created_at    -- γρήγορο feed query (WHERE created_at >= NOW() - 48H)
idx_ads_cook_id       -- my-ads query
idx_requests_ad_id    -- JOIN requests ↔ ads
idx_requests_consumer -- my-requests query
idx_requests_status   -- leaderboard / history queries
```

---

## 3. Backend — PHP API

Κάθε αρχείο είναι ανεξάρτητο endpoint. Η δρομολόγηση γίνεται με `$_GET['action']` και `$_SERVER['REQUEST_METHOD']`.

### `config.php`
- Δημιουργεί `$pdo` (PDO connection με `ERRMODE_EXCEPTION`, πόρτα 3307)
- Καλεί `session_start()`
- Ορίζει `jsonResponse($data, $code)` — helper για JSON output
- Ορίζει `requireAuth()` — επιστρέφει 401 αν δεν υπάρχει session

### `auth.php`

| Method | action | Λειτουργία |
|---|---|---|
| POST | `register` | Έλεγχος duplicate email/username, INSERT με 5 credits |
| POST | `login` | SELECT by email, δημιουργία `$_SESSION`, επιστροφή user object (+ avatar_path) |
| POST | `logout` | `session_destroy()` |
| POST | `upload-avatar` | Ανέβασμα φωτογραφίας προφίλ (multipart/form-data, max 2MB, image/* μόνο) |
| GET  | `me` | Επιστρέφει fresh user data από DB (ανανεώνει credits στο session) |

**Σημείωση ασφαλείας:** Η σύνδεση γίνεται μόνο με email, χωρίς password — απλοποιημένο για ακαδημαϊκό project.

### `ads.php`

| Method | action | Auth | Λειτουργία |
|---|---|---|---|
| GET | `feed` | — | SELECT από active_ads. Αν υπάρχουν `?lat=&lng=`, υπολογίζει Haversine απόσταση και ταξινομεί κοντινότερα πρώτα. Αν υπάρχει `?km=`, φιλτράρει εντός ακτίνας. |
| GET | `my-ads` | ✓ | SELECT WHERE cook_id = session user |
| GET | `view` | — | SELECT by id |
| POST | `create` | ✓ | INSERT νέα αγγελία — **multipart/form-data** (εικόνα + latitude/longitude) |
| PUT | — | ✓ | UPDATE πεδία αγγελίας (έλεγχος ιδιοκτησίας) |
| DELETE | — | ✓ | DELETE αγγελία (CASCADE διαγράφει και τα requests) |

**Haversine formula (PHP):**
```php
function haversine($lat1, $lon1, $lat2, $lon2): float {
    $R = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}
```

### `requests.php`

| Method | action | Auth | Λειτουργία |
|---|---|---|---|
| GET | `my-requests` | ✓ | Παραγγελίες του consumer |
| GET | `incoming` | ✓ | Παραγγελίες που δέχτηκε ο cook |
| GET | `history` | ✓ | Ολοκληρωμένες του consumer |
| GET | `cook-history` | ✓ | Ολοκληρωμένες του cook |
| POST | `create` | ✓ | Transaction: αφαίρεση credits + μερίδων + INSERT request |
| PUT | `approve` | ✓ | status `pending` → `approved` |
| PUT | `reject` | ✓ | Transaction: επιστροφή credits + μερίδων + status `rejected` |
| PUT | `rate` | ✓ | Transaction: πληρωμή μάγειρα + status `picked_up` |

### `stats.php`

| Method | action | Auth | Λειτουργία |
|---|---|---|---|
| GET | `leaderboard` | — | Top 10 μάγειρες κατά picked_up count |
| GET | `stats` | — | Γενικά στατιστικά (γεύματα/αγγελίες/χρήστες) |
| GET | `user-stats` | ✓ | Στατιστικά συγκεκριμένου χρήστη |
| GET | `admin-stats` | ✓ admin | Μερίδες/μήνα, χρήστες, top donor, top γεύματα, μηνιαίο ιστορικό 6 μηνών |

**`admin-stats` response:**
```json
{
  "portions_last_month": 42,
  "total_users": 6,
  "active_ads": 3,
  "top_donor": { "username": "marios_cook", "portions_given": 12 },
  "top_rated_meals": [ { "title": "...", "cook_name": "...", "avg_rating": 4.8, "review_count": 5 } ],
  "monthly_portions": [ { "month": "2026-01", "portions": 10 }, ... ]
}
```

---

## 4. Frontend — Vanilla JavaScript

### Δομή

```
initApp()
├── setupAuthForms()       — register/login forms + toggle
├── setupTabs()            — εναλλαγή sections (+ φόρτωση admin dashboard)
├── setupNewAdModal()      — modal για νέα αγγελία
├── setupOrderModal()      — modal παραγγελίας με quantity controls
├── setupAdForm()          — submit νέας αγγελίας (εικόνα + GPS)
├── setupFeedControls()    — list/map toggle + distance sort με geolocation
├── setupLogout()          — αποσύνδεση
├── setupProfileNavBtn()   — avatar button → profile tab
└── setupAvatarUpload()    — upload φωτογραφίας προφίλ
```

### State

```javascript
let currentUser  = null;    // αποθηκεύεται και στο localStorage
let adsCache     = {};      // adId → ad object — για γρήγορο access στο order modal
let currentAds   = [];      // τελευταία φορτωμένη λίστα αγγελιών (για map sync)
let leafletMap   = null;    // Leaflet map instance (αρχικοποιείται μία φορά)
let mapMarkers   = [];      // τρέχοντα Leaflet markers — καθαρίζονται σε κάθε reload
let userLat      = null;    // GPS τοποθεσία χρήστη (αν δοθεί permission)
let userLng      = null;
```

Το `currentUser` αποθηκεύεται στο `localStorage` ώστε να επιβιώνει το page refresh χωρίς νέο login.

### Κύκλος δεδομένων

```
showMainApp()
├── loadAds()         → GET /ads.php?action=feed[&lat=&lng=]  → renderAds() + updateMapMarkers()
├── loadRequests()    → GET /requests.php?action=...          → renderCookRequests() ή renderConsumerRequests()
└── loadLeaderboard() → GET /stats.php?action=leaderboard     → renderLeaderboard()
```

Κάθε action (παραγγελία, έγκριση, βαθμολογία) καλεί `loadAds()` + `loadRequests()` μετά για να ανανεωθεί το UI.

### `ensureSession()`

Πριν από κάθε authenticated request, καλείται `ensureSession()`:
- Ελέγχει `GET /auth.php?action=me`
- Αν πάρει 401 (session έληξε), κάνει re-login με το αποθηκευμένο email
- Έτσι ο χρήστης δεν αποσυνδέεται αυτόματα αν ο Apache κάνει restart

### Χάρτης (Leaflet)

```javascript
initLeafletMap()         // δημιουργεί χάρτη Leaflet στο #map-container (Athens center)
updateMapMarkers(ads)    // καθαρίζει παλιά markers, προσθέτει νέα με L.divIcon
switchFeedView('map')    // κρύβει #ads-container, εμφανίζει #map-container
switchFeedView('list')   // αντίστροφα
```

Κάθε marker χρησιμοποιεί `L.divIcon` με `.map-marker` CSS class. Η `.map-marker-inactive` class εφαρμόζεται σε αγγελίες με `current_state === 'Inactive'`. Click σε marker ανοίγει popup με κουμπί παραγγελίας.

### Admin Dashboard

```javascript
loadAdminDashboard()          // GET stats.php?action=admin-stats → renderAdminDashboard()
renderAdminDashboard(data)    // γεμίζει #admin-portions, #admin-users, #admin-active-ads,
                              // #admin-top-donor, #admin-top-rated, #admin-monthly (bar chart)
```

Το admin tab (`#admin-tab-btn`) είναι hidden by default. Εμφανίζεται μόνο αν `currentUser.role === 'admin'`.

### Modals

**Order Modal** — εμφανίζεται με `openOrderModal(adId)`:
- Διαβάζει τα δεδομένα από το `adsCache` (χωρίς νέο API call)
- Live υπολογισμός κόστους καθώς αλλάζει quantity
- Disable του + button όταν φτάσει το max available
- Στο submit: `POST /requests.php?action=create` → ενημέρωση credits display

**New Ad Modal** — form με όλα τα πεδία αγγελίας + επιλογή εικόνας + κουμπί GPS, submit → `POST /ads.php?action=create` (FormData)

### File Uploads

Υπάρχουν δύο τύποι upload:

| Τύπος | Endpoint | Φάκελος | Validation |
|---|---|---|---|
| Εικόνα αγγελίας | `POST /ads.php?action=create` | `uploads/` | image/*, max 5MB |
| Avatar χρήστη | `POST /auth.php?action=upload-avatar` | `uploads/avatars/` | image/*, max 2MB |

**Κοινή λογική PHP:**
1. Έλεγχος `$_FILES[...]['error']`
2. Επαλήθευση MIME type με `finfo` (όχι extension — αποτρέπει πλαστά MIME)
3. Έλεγχος μεγέθους αρχείου
4. `move_uploaded_file()` → φάκελος uploads (δημιουργείται αυτόματα με `mkdir(..., true)`)
5. Αποθήκευση σχετικής διαδρομής στη DB (π.χ. `uploads/foto_abc123.jpg`)

**Frontend εμφάνιση:** Η διαδρομή από τη DB είναι σχετική από τη ρίζα του project, οπότε το frontend τη διαβάζει ως `../${path}` (ένα επίπεδο πάνω από το `frontend/`).

**Avatar στο UI:** Μετά το upload, η `updateAvatarDisplay(path)` αλλάζει την εικόνα τόσο στο header button όσο και στο profile card. Αν δεν υπάρχει avatar, εμφανίζονται τα initials του username.

### XSS Protection

Όλο το δυναμικό HTML περνάει από `escHtml()`:
```javascript
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str)));
    return d.innerHTML;
}
```

---

## 5. Σύστημα Credits

| Ενέργεια | Αλλαγή Credits |
|---|---|
| Εγγραφή | **+5** (νέος χρήστης) |
| Παραγγελία (qty μερίδες) | **−(credit_costs × qty)** |
| Απόρριψη από μάγειρα | **+(credit_costs × qty)** επιστροφή |
| Βαθμολογία ≤ 3 ⭐ → Μάγειρας | **+(credit_costs × qty)** |
| Βαθμολογία > 3 ⭐ → Μάγειρας | **+((credit_costs + 1) × qty)** bonus |
| No-show ποινή | **−1** |

**Παράδειγμα:** Φαγητό 2 credits/μερίδα, 3 μερίδες, rating 5⭐:
- Καταναλωτής πληρώνει: **−6 credits**
- Μάγειρας παίρνει: **(2+1) × 3 = +9 credits**

Κάθε συναλλαγή γίνεται μέσα σε **database transaction** (BEGIN → COMMIT / ROLLBACK) ώστε να μην χαθούν credits αν κοπεί η σύνδεση στη μέση.

---

## 6. Κύκλος Παραγγελίας

```
[Καταναλωτής] Παραγγελία
        │
        ▼
   status: pending
   credits αφαιρούνται αμέσως
   available_portions μειώνεται
        │
        ▼
[Μάγειρας] Αποδοχή ή Άρνηση
     ┌────┴────┐
     ▼         ▼
  approved   rejected
     │      credits + μερίδες
     │      επιστρέφουν
     ▼
[Καταναλωτής] Βαθμολογία 1-5⭐
        │
        ▼
   status: picked_up
   Μάγειρας πληρώνεται
   (+ bonus αν rating > 3)
```

---

## 7. CSS — Design System

Βασίζεται σε **CSS custom properties** (variables):

```css
--primary:       #07662f   /* σκούρο πράσινο — brand color */
--primary-dark:  #054a22
--primary-light: #098f40
--secondary:     #f5a623   /* πορτοκαλί — pending state */
--danger:        #e74c3c   /* κόκκινο — rejected / delete */
--success:       #27ae60   /* πράσινο — approved / completed */
```

**Component classes:**
- `.food-card` — κάρτα αγγελίας με hover animation
- `.food-card.card-inactive` — opacity 0.62 + grayscale(0.4) για αγγελίες χωρίς μερίδες
- `.request-card.{pending|approved|completed|rejected}` — χρωματιστό border-left
- `.toast` — floating notification (bottom center, auto-dismiss 3.5s)
- `.modal` → `.modal.active` — overlay με flex centering
- `.feed-controls` — row με view toggle + sort select
- `.view-btn.active` — ενεργό κουμπί list/map
- `.map-container` — `height: 420px`, wrapper για Leaflet χάρτη
- `.map-marker` / `.map-marker-inactive` — custom HTML markers στον χάρτη
- `.admin-stats-grid` — CSS Grid auto-fit για τα stat cards
- `.admin-stat-card` — κάρτα με icon + value + label
- `.monthly-bars` / `.monthly-bar` — inline bar chart με % πλάτος
- `.profile-avatar` — κλικ για upload, hover εμφανίζει `.avatar-edit-overlay`

**Responsive:** grid 1→2→3 columns για τα food cards (`@media 600px / 768px`).

---

## 8. Αρχεία — Σύνοψη

| Αρχείο | Γραμμές (κατά προσέγγιση) | Ρόλος |
|---|---|---|
| `frontend/index.html` | ~350 | Όλο το HTML markup (login + app + modals) |
| `frontend/style.css` | ~950 | Design system, components, map, admin, avatar styles |
| `frontend/script.js` | ~1100 | Όλη η frontend λογική (map, admin, distance sort, avatar upload) |
| `backend/config.php` | ~45 | DB connection (πόρτα 3307), helpers |
| `backend/auth.php` | ~210 | register / login / logout / me / upload-avatar |
| `backend/ads.php` | ~190 | CRUD αγγελιών + image upload + Haversine distance |
| `backend/requests.php` | ~230 | Παραγγελίες + transactions |
| `backend/stats.php` | ~190 | Leaderboard + στατιστικά + admin dashboard |
| `backend/unibite.sql` | ~190 | Schema + procedures + seed data (με GPS coords + admin user) |
| `uploads/` | — | Εικόνες αγγελιών (δημιουργείται αυτόματα) |
| `uploads/avatars/` | — | Φωτογραφίες προφίλ (δημιουργείται αυτόματα) |
