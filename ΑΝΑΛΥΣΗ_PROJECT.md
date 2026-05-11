# UniBite — Πλήρης Τεχνική Ανάλυση

---

## 1. Τι είναι το UniBite

Πλατφόρμα ανταλλαγής φαγητού για φοιτητές. Οι **μάγειρες** δημοσιεύουν αγγελίες με φαγητό που έχουν μαγειρέψει. Οι **καταναλωτές** το βλέπουν στο feed και κάνουν παραγγελία πληρώνοντας με **Credits** (εικονικό νόμισμα). Ο μάγειρας εγκρίνει ή απορρίπτει. Ο καταναλωτής παραλαμβάνει και βαθμολογεί — τότε ο μάγειρας πληρώνεται.

---

## 2. Αρχιτεκτονική (3 επίπεδα)

```
┌─────────────────────────────────────────────┐
│              BROWSER (Client)               │
│  index.html + style.css + script.js         │
│  • Αποθηκεύει χρήστη: localStorage          │
│  • Επικοινωνία με backend: fetch() (JSON)   │
└───────────────────┬─────────────────────────┘
                    │ HTTP (GET/POST/PUT)
                    │ JSON request/response
┌───────────────────▼─────────────────────────┐
│           APACHE / PHP (Server)             │
│  config.php  auth.php  ads.php              │
│  requests.php  stats.php                    │
│  • Έλεγχος session ($_SESSION)              │
│  • Επικύρωση δεδομένων                      │
│  • Εκτέλεση SQL μέσω PDO                   │
└───────────────────┬─────────────────────────┘
                    │ PDO / SQL
┌───────────────────▼─────────────────────────┐
│         MariaDB 10.4 (XAMPP, port 3307)     │
│  users   ads   requests                     │
│  Views + Stored Procedures + Indexes        │
└─────────────────────────────────────────────┘
```

**Πώς τρέχει:** Ο χρήστης ανοίγει `http://localhost/unibite/frontend/`. Ο Apache σερβίρει το `index.html`. Κάθε ενέργεια (εγγραφή, παραγγελία κλπ.) στέλνει αίτημα στα PHP αρχεία του `backend/`. Αυτά τρέχουν στον Apache, επικοινωνούν με τη MariaDB, και επιστρέφουν JSON.

---

## 3. Βάση Δεδομένων (Schema)

### Πίνακας `users`

| Στήλη | Τύπος | Σημείωση |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | — |
| `username` | VARCHAR(50) NOT NULL | — |
| `email` | VARCHAR(100) UNIQUE NOT NULL | Μοναδικό — χρησιμοποιείται ως login |
| `role` | ENUM('cook','consumer','admin') | Καθορίζει τι βλέπει ο χρήστης |
| `credits` | INT DEFAULT 5 | Αρχίζει με 5 δωρεάν |
| `created_at` | TIMESTAMP DEFAULT NOW() | — |

### Πίνακας `ads`

| Στήλη | Τύπος | Σημείωση |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | — |
| `cook_id` | INT FK → users.id CASCADE | Ποιος δημοσίευσε |
| `title` | VARCHAR(100) | — |
| `credit_costs` | INT DEFAULT 1 | Credits/μερίδα |
| `description` | TEXT | — |
| `total_portions` | INT | Αρχικές μερίδες |
| `available_portions` | INT | Μειώνεται με κάθε παραγγελία |
| `allergens` | TEXT | — |
| `pickup_location` | VARCHAR(255) | — |
| `pickup_time` | VARCHAR(100) | — |
| `lat`, `lng` | DECIMAL | Για μελλοντική χρήση (χάρτης) |
| `created_at` | TIMESTAMP DEFAULT NOW() | Χρησιμοποιείται για φιλτράρισμα 48ω |

### Πίνακας `requests`

| Στήλη | Τύπος | Σημείωση |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | — |
| `ad_id` | INT FK → ads.id CASCADE | Ποια αγγελία |
| `consumer_id` | INT FK → users.id CASCADE | Ποιος παράγγειλε |
| `quantity` | INT DEFAULT 1 | Πόσες μερίδες |
| `status` | ENUM('pending','approved','rejected','picked_up','no_show') | Κύκλος ζωής |
| `rating` | INT (1-5) NULL | Null μέχρι να βαθμολογήσει |
| `received_at` | TIMESTAMP NULL | Πότε ολοκληρώθηκε |
| `created_at` | TIMESTAMP DEFAULT NOW() | — |

### Σχέσεις (ERD)

```
users ──< ads         (ένας μάγειρας → πολλές αγγελίες)
ads   ──< requests    (μια αγγελία  → πολλές παραγγελίες)
users ──< requests    (ένας consumer → πολλές παραγγελίες)
```

Το `ON DELETE CASCADE` σημαίνει: αν διαγραφεί ένας χρήστης, διαγράφονται οι αγγελίες του. Αν διαγραφεί αγγελία, διαγράφονται και οι παραγγελίες της.

### Indexes

```sql
idx_ads_created_at        -- Γρήγορο φιλτράρισμα feed (48ω)
idx_ads_cook_id           -- Γρήγορο "οι αγγελίες μου"
idx_requests_ad_id        -- JOIN requests → ads
idx_requests_consumer_id  -- "οι παραγγελίες μου"
idx_requests_status       -- φιλτράρισμα ανά κατάσταση
```

### Views

```sql
-- active_ads: αγγελίες < 48 ώρες με cook_name και current_state
-- leaderboard: μάγειρες ταξινομημένοι κατά πλήθος picked_up requests
```

### Stored Procedures

| Procedure | Τι κάνει |
|---|---|
| `create_request(ad_id, consumer_id, qty)` | Δημιουργεί παραγγελία: αφαιρεί credits, μειώνει μερίδες, INSERT |
| `rate_and_pay(request_id, rating)` | Βαθμολογεί: υπολογίζει ανταμοιβή, πληρώνει μάγειρα |
| `handle_no_show(consumer_id, ad_id)` | No-show: ποινή -1 credit, επιστροφή μερίδας |

> Οι procedures υπάρχουν στη βάση αλλά η PHP τρέχει τα ίδια SQL steps inline (με transactions). Είναι εναλλακτική υλοποίηση — χρήσιμες για documentation.

---

## 4. Backend — PHP Αρχεία

### `config.php` — Κεντρικό αρχείο υποδομής

**Περιλαμβάνεται** (`require_once 'config.php'`) από όλα τα άλλα PHP αρχεία.

Κάνει 3 πράγματα:
1. **Σύνδεση με DB:** Δημιουργεί `$pdo` (PDO object), global μεταβλητή
2. **`session_start()`:** Ενεργοποιεί sessions σε κάθε request
3. **Helper functions:**
   - `jsonResponse($data, $code=200)` — στέλνει JSON response και τερματίζει
   - `requireAuth()` — ελέγχει `$_SESSION['user_id']`, αλλιώς 401
   - `requireAdmin()` — ελέγχει `$_SESSION['role'] === 'admin'`, αλλιώς 403

**Κρίσιμο:** Στην PHP, οι συναρτήσεις δεν βλέπουν global μεταβλητές εκτός αν γραφτεί `global $pdo;` στην αρχή τους. Χωρίς αυτό, το `$pdo` είναι `null` και η εφαρμογή πέφτει με fatal error.

---

### `auth.php` — Αυθεντικοποίηση

**Δρομολόγηση:**
- `POST ?action=register` → `registerUser()`
- `POST ?action=login` → `loginUser()`
- `POST ?action=logout` → `logoutUser()`
- `GET  ?action=me` → `getCurrentUser()`

**`registerUser()`**
- Διαβάζει JSON από `php://input` (όχι `$_POST` — ο client στέλνει `Content-Type: application/json`)
- Ελέγχει αν υπάρχει email/username
- Εισάγει χρήστη με `credits = 5`
- Επιστρέφει 201 με τα στοιχεία του χρήστη

**`loginUser()`**
- Αναζητά χρήστη με email
- Γεμίζει το `$_SESSION` με `user_id`, `username`, `email`, `role`, `credits`
- Δεν υπάρχει password — authentication μόνο με email

**`getCurrentUser()`**
- Διαβάζει από `$_SESSION`
- Χρησιμοποιείται από `ensureSession()` στο JS για να ελεγχθεί αν το session είναι ζωντανό

---

### `ads.php` — Αγγελίες Φαγητού

| Method | Action | Συνάρτηση | Auth |
|---|---|---|---|
| GET | `feed` | `getActiveAds()` | Όχι |
| GET | `my-ads` | `getMyAds()` | Ναι |
| GET | `view` + `?id=` | `getAdById()` | Όχι |
| POST | `create` | `createAd()` | Ναι |
| PUT | — | `updateAd()` | Ναι |
| DELETE | — + `?id=` | `deleteAd()` | Ναι |

**Feed query (core logic):**
```sql
SELECT a.*, u.username as cook_name,
    CASE WHEN a.available_portions > 0 THEN 'Active' ELSE 'Inactive' END as current_state
FROM ads a
JOIN users u ON a.cook_id = u.id
WHERE a.created_at >= NOW() - INTERVAL 48 HOUR
ORDER BY a.created_at DESC
```
Το `current_state` υπολογίζεται inline (CASE) και χρησιμοποιείται στο frontend για να δείχνει "Διαθέσιμο" ή "Εξαντλήθηκε".

**Ownership check:** Πριν update/delete, ελέγχει `$ad['cook_id'] != $_SESSION['user_id']` και επιστρέφει 403 αν δεν ανήκει στον τρέχοντα χρήστη.

---

### `requests.php` — Παραγγελίες & Transactions

Το πιο σύνθετο αρχείο. Διαχειρίζεται ολόκληρο τον κύκλο ζωής μιας παραγγελίας.

| Method | Action | Συνάρτηση | Για ποιον |
|---|---|---|---|
| GET | `my-requests` | `getMyRequests()` | Καταναλωτής |
| GET | `incoming` | `getIncomingRequests()` | Μάγειρας |
| GET | `history` | `getRequestHistory()` | Καταναλωτής |
| GET | `cook-history` | `getCookHistory()` | Μάγειρας |
| POST | `create` | `createRequest()` | Καταναλωτής |
| PUT | `approve` | `approveRequest()` | Μάγειρας |
| PUT | `reject` | `rejectRequest()` | Μάγειρας |
| PUT | `rate` | `rateRequest()` | Καταναλωτής |

**`createRequest()` — DB Transaction:**
```
BEGIN TRANSACTION
  UPDATE users SET credits = credits - totalCost WHERE id = consumer_id
  UPDATE ads SET available_portions = available_portions - qty WHERE id = ad_id
  INSERT INTO requests (ad_id, consumer_id, quantity, status='pending')
COMMIT
```
Αν οποιοδήποτε βήμα αποτύχει → `ROLLBACK`. Δεν κινδυνεύει να αφαιρεθούν credits χωρίς να δημιουργηθεί παραγγελία.

**`rejectRequest()` — Επιστροφή:**
```
BEGIN TRANSACTION
  UPDATE users SET credits = credits + refund WHERE id = consumer_id   ← επιστροφή credits
  UPDATE ads SET available_portions = available_portions + qty          ← επιστροφή μερίδων
  UPDATE requests SET status = 'rejected' WHERE id = request_id
COMMIT
```

**`rateRequest()` — Πληρωμή Μάγειρα:**
```
reward = rating > 3 ? (credit_costs + 1) × qty : credit_costs × qty
BEGIN TRANSACTION
  UPDATE users SET credits = credits + reward WHERE id = cook_id
  UPDATE requests SET status = 'picked_up', rating = ?, received_at = NOW()
COMMIT
```

---

### `stats.php` — Στατιστικά & Leaderboard

| Action | Συνάρτηση | Τι επιστρέφει |
|---|---|---|
| `leaderboard` | `getLeaderboard()` | Top 10 μάγειρες κατά αριθμό picked_up |
| `stats` | `getStats()` | successful_meals (μήνα), active_ads, total_users |
| `user-stats` | `getUserStats()` | given, received, avg_rating_given, avg_rating_received |

---

## 5. Frontend

### HTML (`index.html`) — SPA Δομή

Ένα αρχείο HTML, δύο "σελίδες" (toggle visibility):

```
#login-page     → Φόρμες εγγραφής/σύνδεσης
#main-app       → Ολόκληρη η εφαρμογή (κρυμμένη αρχικά)
```

**Δομή `#main-app`:**
```
<header>          Logo + Credits badge + Avatar button (→ Προφίλ)
<nav.nav-tabs>    5 tabs: Feed | Αγγελίες | Αιτήματα | Top | Προφίλ
<main>
  #feed-section         → #ads-container (δυναμικό)
  #my-ads-section       → Δημοσιευμένες αγγελίες + κουμπί "Νέα Αγγελία"
  #requests-section     → Αιτήματα (διαφορετικό layout ανά ρόλο)
  #leaderboard-section  → .leaderboard
  #profile-section      → Στοιχεία + ιστορικό + αποσύνδεση
```

**Modals:**
```
#order-modal      → Επιλογή μερίδων + επιβεβαίωση παραγγελίας
#new-ad-modal     → Φόρμα δημιουργίας αγγελίας
```

Τα modals εμφανίζονται/κρύβονται με `.classList.add('active')`.

---

### CSS (`style.css`) — Design System

**CSS Custom Properties (variables):**
```css
--primary    #FF6B35   /* Πορτοκαλί - κύριο χρώμα */
--secondary  #4ECDC4   /* Τυρκουάζ */
--success    #2ECC71   /* Πράσινο */
--danger     #E74C3C   /* Κόκκινο */
--warning    #F39C12   /* Κίτρινο */
--bg-primary #FFF8F0   /* Ανοιχτό φόντο */
```

**Βασικά components:**
- `.food-card` — Κάρτα αγγελίας στο feed
- `.request-card.pending/.approved/.completed/.rejected` — Χρωματιστές κάρτες αιτημάτων
- `.profile-card` — Avatar + username + role badge
- `.profile-info-grid` — Πλέγμα πληροφοριών (email, credits, ημερομηνία)
- `.history-item` — Γραμμή ιστορικού
- `.order-toast.success/.error` — Toast notifications (bottom-right, CSS transition)
- `.modal` / `.modal.active` — Overlay modals
- `.quantity-controls` / `.qty-btn` — +/- κουμπιά modal παραγγελίας

---

### JavaScript (`script.js`) — Αρχιτεκτονική

#### Global State
```javascript
let currentUser = null;   // Object με id, username, email, role, credits
let adsCache = {};         // { adId: adObject } — in-memory cache από loadAds()
```

#### Εκκίνηση (`initApp`)
```
DOMContentLoaded
  → initApp()
      → localStorage.getItem('unibite_user')
          ✓ υπάρχει  → showMainApp() + loadAds() + loadRequests() + loadLeaderboard()
          ✗ δεν υπάρχει → showLoginPage()
      → setupAuthForms()
      → setupTabs()
      → setupModal()       (new-ad modal)
      → setupOrderModal()  (order confirmation modal)
      → setupAdForm()
      → setupLogout()
      → setupProfileNavBtn()
```

#### Dual-Layer Authentication

**Στρώμα 1 — localStorage:** Κρατά τον χρήστη μεταξύ page refreshes. Δεν χρειάζεται login σε κάθε επίσκεψη.

**Στρώμα 2 — PHP Session:** Κάθε protected endpoint ελέγχει `$_SESSION['user_id']`. Sessions λήγουν (π.χ. server restart, timeout).

**`ensureSession()`:** Επιλύει τη διένεξη:
```javascript
async function ensureSession() {
    const res = await fetch('backend/auth.php?action=me');
    if (res.status === 401 && currentUser) {
        // Session έληξε, αλλά ξέρουμε το email από localStorage
        await fetch('backend/auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ email: currentUser.email })
        });
        // Τώρα το PHP session είναι ξανά ενεργό
    }
}
```
Καλείται πριν από κάθε προστατευμένη ενέργεια (παραγγελία, έγκριση, βαθμολογία).

#### Role-based Rendering

Το ίδιο JS αρχείο αποδίδει διαφορετικό UI ανάλογα με `currentUser.role`:

```javascript
// Αιτήματα
if (isCook) {
    titleEl.textContent = 'Εισερχόμενες Παραγγελίες';
    renderCookRequests(data.requests);    // Approve/Reject buttons
} else {
    titleEl.textContent = 'Οι Παραγγελίες μου';
    renderConsumerRequests(data.requests); // Status + Rating stars
}

// Ιστορικό
const action = isCook ? 'cook-history' : 'history';
renderHistory(data.history, isCook);     // Διαφορετικό template
```

#### `adsCache` — Γιατί υπάρχει

Όταν ο χρήστης πατά "Παραγγελία" σε μια κάρτα φαγητού, το modal χρειάζεται πληροφορίες γι' αυτό το φαγητό (τίτλος, κόστος, διαθέσιμες μερίδες). Αντί να κάνει νέο HTTP request, διαβάζει από το `adsCache` που έχει ήδη φορτωθεί από το `loadAds()`:

```javascript
function orderFood(adId) {
    const ad = adsCache[adId];   // 0ms — από μνήμη
    // populate modal...
}
```

#### Order Modal — Live Cost Calculation

```javascript
plusBtn.addEventListener('click', () => {
    const ad = adsCache[modal.dataset.adId];
    const qty = Math.min(ad.available_portions, parseInt(qtyEl.textContent) + 1);
    qtyEl.textContent = qty;
    costEl.textContent = `${ad.credit_costs * qty} 🪙`;   // live update
    minusBtn.disabled = qty <= 1;
    plusBtn.disabled  = qty >= ad.available_portions;
});
```

#### Toast Notifications

Αντί για `alert()`, δημιουργούνται και διαγράφονται δυναμικά:
```javascript
function showOrderSuccess(title, qty, cost) {
    const msg = document.createElement('div');
    msg.className = 'order-toast success';
    msg.textContent = `✅ Παραγγελία για "${title}" (${qty} μερίδα) — -${cost} 🪙`;
    document.body.appendChild(msg);
    setTimeout(() => msg.classList.add('show'), 10);      // CSS transition trigger
    setTimeout(() => { msg.classList.remove('show');
        setTimeout(() => msg.remove(), 400); }, 3500);   // fade out + remove
}
```

---

## 6. Κύκλος Ζωής Παραγγελίας

```
CONSUMER                    SERVER                      COOK
────────                    ──────                      ────
[πατά Παραγγελία]
  → orderFood(adId)
  → reads adsCache[adId]
  → ανοίγει modal
  → επιλέγει qty
  → πατά Επιβεβαίωση

  → ensureSession()
  → POST /requests.php
    ?action=create
    { ad_id, quantity }
                            ← ελέγχει credits
                            ← ελέγχει μερίδες
                            ← BEGIN TRANSACTION
                            ←   credits consumer - cost
                            ←   portions - qty
                            ←   INSERT requests (pending)
                            ← COMMIT
                            → 201 { transaction }

  ← credits update UI
  ← toast success
  ← loadAds() + loadRequests()

                                                  [βλέπει εισερχόμενο]
                                                  [πατά Αποδοχή]
                                                  → PUT ?action=approve
                            ← UPDATE status=approved
                            → 200 OK
                                                  ← toast + loadRequests()

  [βλέπει Εγκρίθηκε]
  [πηγαίνει να παραλάβει]
  [πατά ⭐⭐⭐⭐]
  → rateRequest(id, 4)
  → PUT ?action=rate
    { request_id, rating: 4 }
                            ← reward = (cost+1)×qty  [rating>3]
                            ← BEGIN TRANSACTION
                            ←   credits cook + reward
                            ←   UPDATE status=picked_up, rating=4
                            ← COMMIT
                            → 200 { reward }

  ← toast "Μάγειρας πήρε +X 🪙"
  ← loadRequests()
                                                  [βλέπει picked_up + credits]
```

---

## 7. Σύστημα Credits

| Ενέργεια | Credits |
|---|---|
| Εγγραφή | +5 |
| Παραγγελία (qty μερίδες × cost/μερίδα) | −(cost × qty) |
| Απόρριψη από μάγειρα | +(cost × qty) επιστροφή |
| Βαθμολογία ≤ 3 αστέρια → Μάγειρας | +(cost × qty) |
| Βαθμολογία > 3 αστέρια → Μάγειρας | +((cost + 1) × qty) bonus |
| No-show ποινή | −1 |

**Παράδειγμα:** Φαγητό 2 credits/μερίδα, παραγγελία 3 μερίδες, rating 5/5:
- Consumer πληρώνει: −6 credits
- Cook παίρνει: (2+1) × 3 = +9 credits

---

## 8. Χάρτης Εξαρτήσεων

```
index.html
  └── style.css         (styling)
  └── script.js
        ├── backend/auth.php
        │     └── config.php → MariaDB (users)
        ├── backend/ads.php
        │     └── config.php → MariaDB (ads, users JOIN)
        ├── backend/requests.php
        │     └── config.php → MariaDB (requests, ads JOIN, users)
        └── backend/stats.php
              └── config.php → MariaDB (requests, ads, users)
```

**Εξωτερικές εξαρτήσεις:**
- Google Fonts API (Poppins) — χρειάζεται internet
- Κανένα JS framework — vanilla JS
- Κανένα PHP framework — plain PDO

---

## 9. Περιβάλλον Εκτέλεσης

**Αυτός ο υπολογιστής:**
- XAMPP MariaDB 10.4 → port **3307** (λόγω conflict με MySQL 8.x στο 3306)
- Apache σερβίρει από `C:\xampp\htdocs\`
- Junction link: `C:\xampp\htdocs\unibite` → `C:\Users\User\Unibite`
- Έτσι τα αρχεία παραμένουν στον αρχικό φάκελο αλλά είναι προσβάσιμα από τον Apache

**Σε νέο υπολογιστή (standard XAMPP):**
- MySQL → port **3306**
- Αρχεία: `C:\xampp\htdocs\Unibite\`
- `config.php`: `$port = 3306;`

---

## 10. Βασικές Τεχνικές Αποφάσεις

| Απόφαση | Επιλογή | Λόγος |
|---|---|---|
| Auth χωρίς password | Email μόνο | Απλοποίηση για demo — χωρίς hashing, reset flows |
| Dual-layer auth | localStorage + PHP session | localStorage → persistence, session → server-side security |
| `ensureSession()` | Silent re-login | Ο χρήστης δεν ξέρει ότι το session έληξε |
| Transactions | `beginTransaction/commit/rollBack` | Atomicity — αν πέσει ο server στη μέση δεν χάνονται credits |
| `adsCache` | In-memory object | Αποφεύγει extra HTTP request για κάθε modal άνοιγμα |
| SPA pattern | Ένα HTML, CSS show/hide | Χωρίς reload μεταξύ tabs — καλύτερο UX |
| Role-based rendering | `currentUser.role` check στο JS | Ένα αρχείο JS, δύο εμπειρίες (cook / consumer) |
| `php://input` | Αντί για `$_POST` | Ο client στέλνει `application/json` — το `$_POST` είναι κενό |
| `global $pdo;` | Σε κάθε συνάρτηση | PHP scope rule — globals δεν κληρονομούνται από functions |
| Feed 48ω | `WHERE created_at >= NOW() - INTERVAL 48 HOUR` | Αυτόματη λήξη χωρίς cron jobs ή soft delete |

---

## 11. Stack Τεχνολογιών

| Επίπεδο | Τεχνολογία | Έκδοση |
|---|---|---|
| Frontend | HTML5 + CSS3 + Vanilla JS | — |
| Fonts | Google Fonts (Poppins) | — |
| Web Server | Apache | 2.4 (via XAMPP) |
| Backend | PHP | 8.2 |
| DB Driver | PDO (PHP Data Objects) | built-in |
| Database | MariaDB | 10.4 (via XAMPP) |
| OS | Windows 11 | — |
| Dev env | XAMPP | — |

---

## 12. Αρχεία Project

```
Unibite/
├── frontend/
│   ├── index.html      ← SPA: login + main app σε ένα αρχείο
│   ├── style.css       ← CSS variables, components, modals, toasts
│   └── script.js       ← Όλη η λογική frontend (~795 γραμμές)
├── backend/
│   ├── config.php      ← DB connection + helper functions (κεντρικό)
│   ├── auth.php        ← register / login / logout / me
│   ├── ads.php         ← feed / my-ads / create / update / delete
│   ├── requests.php    ← create / approve / reject / rate / history
│   ├── stats.php       ← leaderboard / stats / user-stats
│   ├── unibite.sql     ← Schema + indexes + views + procedures + test data
│   └── seed_data.sql   ← 8 φρέσκες αγγελίες + reset credits (τρέχει το refresh.bat)
├── refresh.bat         ← Διπλό κλικ: φρέσκα δεδομένα στη βάση
├── erdiagram.png       ← ER διάγραμμα βάσης δεδομένων
├── README.md           ← Τεκμηρίωση project (GitHub)
├── ΟΔΗΓΙΕΣ.md          ← Εκτέλεση σε αυτόν τον υπολογιστή
├── ΕΓΚΑΤΑΣΤΑΣΗ.md      ← Εγκατάσταση από μηδέν (για τρίτο)
└── ΑΝΑΛΥΣΗ_PROJECT.md  ← Αυτό το αρχείο
```

---

## 13. Εργαλεία Ανάπτυξης

### `refresh.bat` — Γρήγορη Επαναφορά Δεδομένων

Το feed εμφανίζει μόνο αγγελίες < 48 ωρών. Για demo ή testing, αρκεί διπλό κλικ στο `refresh.bat`:

1. Ελέγχει αν η MySQL τρέχει στη θύρα 3307
2. Τρέχει το `backend/seed_data.sql` με σωστό UTF-8 encoding
3. Αποτέλεσμα: 8 φρέσκες αγγελίες + credits επαναφορά test χρηστών

### `backend/seed_data.sql` — Τι κάνει

```sql
DELETE FROM ads WHERE id > 5;        -- καθαρισμός παλιών test αγγελιών
UPDATE ads SET created_at = NOW();   -- φρεσκάρισμα υπαρχόντων
INSERT INTO ads (...) VALUES ...     -- 8 νέες αγγελίες με NOW() timestamps
UPDATE users SET credits = 10 WHERE email IN ('katerina@uni.gr', 'nikos@uni.gr');
UPDATE users SET credits = 5  WHERE email IN ('marios@uni.gr', ...);
```
