# 🍎 UniBite — Φοιτητικό Σύστημα Ανταλλαγής Φαγητού

> Μια web εφαρμογή που συνδέει φοιτητές-μάγειρες με φοιτητές-καταναλωτές μέσω ενός συστήματος virtual credits.

---

## Τι είναι το UniBite

Το **UniBite** είναι μια **Single Page Application (SPA)** για φοιτητές. Οι μάγειρες δημοσιεύουν αγγελίες με φαγητό που έφτιαξαν, και οι καταναλωτές παραγγέλνουν πληρώνοντας με **Credits** — ένα εικονικό νόμισμα της πλατφόρμας.

**Πρόβλημα που λύνει:** Φοιτητές που μαγειρεύουν πετούν φαγητό γιατί φτιάχνουν πολλές μερίδες. Άλλοι φοιτητές δεν έχουν χρόνο ή γνώσεις να μαγειρέψουν. Το UniBite τους συνδέει.

---

## Χαρακτηριστικά

### Για Μάγειρες 🍳
- Δημοσίευση αγγελίας με τίτλο, περιγραφή, μερίδες, κόστος credits, αλλεργιογόνα, τοποθεσία & ώρα παραλαβής
- Αποδοχή ή άρνηση εισερχόμενων παραγγελιών
- Αυτόματη πληρωμή σε credits μόλις ο καταναλωτής βαθμολογήσει
- **Bonus credit** για βαθμολογία > 3 αστέρια
- Ιστορικό όλων των παραγγελιών που εκπλήρωσες

### Για Καταναλωτές 🍽️
- Feed με όλα τα διαθέσιμα φαγητά (τελευταίες 48 ώρες)
- Επιλογή αριθμού μερίδων με live υπολογισμό κόστους
- Real-time κατάσταση παραγγελίας (Αναμένει / Εγκρίθηκε / Ολοκληρώθηκε)
- Βαθμολογία 1-5 αστέρων μετά την παραλαβή
- Αυτόματη επιστροφή credits σε περίπτωση άρνησης

### Γενικά
- Εγγραφή με **5 δωρεάν credits**
- Σύνδεση μόνο με email — χωρίς password
- **Leaderboard** με τους top μάγειρες
- Toast notifications (χωρίς browser alerts)
- Αυτόματη λήξη αγγελιών μετά από 48 ώρες

---

## Τεχνολογίες

| Επίπεδο | Τεχνολογία |
|---|---|
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Web Server | Apache 2.4 (μέσω XAMPP) |
| Backend | PHP 8.x |
| Database Driver | PDO |
| Database | MariaDB 10.x (μέσω XAMPP) |

Κανένα framework — ούτε στο frontend (React/Vue) ούτε στο backend (Laravel/Symfony). Plain PHP + Vanilla JS.

---

## Εγκατάσταση

### Προαπαιτούμενα
- Windows 10 / 11
- [XAMPP](https://www.apachefriends.org) (Apache + MariaDB + PHP)

### Βήματα

**1. Εγκατάσταση XAMPP**

Κατέβασε και εγκατέστησε το XAMPP. Ο φάκελος εγκατάστασης πρέπει να είναι `C:\xampp`.

**2. Εκκίνηση XAMPP**

Άνοιξε το XAMPP Control Panel και πάτα **Start** δίπλα στο **Apache** και **MySQL**.

**3. Τοποθέτηση αρχείων**

Αντίγραψε τον φάκελο `Unibite` στο:
```
C:\xampp\htdocs\Unibite\
```

**4. Εισαγωγή βάσης δεδομένων**

```
chcp 65001
C:\xampp\mysql\bin\mysql.exe -u root -P 3306 -h 127.0.0.1 < "C:\xampp\htdocs\Unibite\backend\unibite.sql"
```

> Αν το MySQL τρέχει σε πόρτα 3307, άλλαξε `-P 3306` σε `-P 3307` και ενημέρωσε το `backend/config.php` αντίστοιχα.

**5. Άνοιγμα εφαρμογής**

```
http://localhost/Unibite/frontend/
```

> Μην ανοίγεις το `index.html` με double-click — χρειάζεται τον Apache για το PHP backend.

Για αναλυτικές οδηγίες και αντιμετώπιση προβλημάτων: [ΕΓΚΑΤΑΣΤΑΣΗ.md](ΕΓΚΑΤΑΣΤΑΣΗ.md)

---

## Λογαριασμοί δοκιμής

Σύνδεση μόνο με email — χωρίς password.

| Email | Ρόλος |
|---|---|
| `marios@uni.gr` | Μάγειρας |
| `elena@uni.gr` | Μάγειρας |
| `giorgos@uni.gr` | Μάγειρας |
| `anna@uni.gr` | Μάγειρας |
| `katerina@uni.gr` | Καταναλωτής |
| `nikos@uni.gr` | Καταναλωτής |

---

## Δομή Project

```
Unibite/
├── frontend/
│   ├── index.html        ← SPA: login + εφαρμογή σε ένα αρχείο
│   ├── style.css         ← CSS variables, components, modals, toasts
│   └── script.js         ← Όλη η frontend λογική
├── backend/
│   ├── config.php        ← DB connection + helper functions
│   ├── auth.php          ← register / login / logout / me
│   ├── ads.php           ← feed / my-ads / create / update / delete
│   ├── requests.php      ← create / approve / reject / rate / history
│   ├── stats.php         ← leaderboard / stats / user-stats
│   └── unibite.sql       ← Schema + indexes + views + procedures + seed data
├── refresh.bat           ← Quick database reset
├── README.md
├── ΕΓΚΑΤΑΣΤΑΣΗ.md        ← Αναλυτικές οδηγίες εγκατάστασης
└── ΑΝΑΛΥΣΗ_PROJECT.md    ← Πλήρης τεχνική ανάλυση
```

---

## API Endpoints

Όλα τα endpoints επικοινωνούν με JSON (`Content-Type: application/json`).

### Auth — `backend/auth.php`

| Method | ?action= | Περιγραφή | Auth |
|---|---|---|---|
| POST | `register` | Εγγραφή (+5 credits) | — |
| POST | `login` | Σύνδεση με email | — |
| POST | `logout` | Αποσύνδεση | — |
| GET  | `me` | Τρέχων χρήστης (fresh από DB) | ✓ |

### Ads — `backend/ads.php`

| Method | ?action= | Περιγραφή | Auth |
|---|---|---|---|
| GET | `feed` | Ενεργές αγγελίες (48ω) | — |
| GET | `my-ads` | Αγγελίες του χρήστη | ✓ |
| GET | `view&id=X` | Μια αγγελία | — |
| POST | `create` | Νέα αγγελία | ✓ |
| PUT | — | Ενημέρωση αγγελίας | ✓ |
| DELETE | `?id=X` | Διαγραφή αγγελίας | ✓ |

### Requests — `backend/requests.php`

| Method | ?action= | Περιγραφή | Για ποιον |
|---|---|---|---|
| GET | `my-requests` | Παραγγελίες καταναλωτή | Καταναλωτής |
| GET | `incoming` | Εισερχόμενες παραγγελίες | Μάγειρας |
| GET | `history` | Ιστορικό καταναλωτή | Καταναλωτής |
| GET | `cook-history` | Ιστορικό μάγειρα | Μάγειρας |
| POST | `create` | Νέα παραγγελία | Καταναλωτής |
| PUT | `approve` | Έγκριση | Μάγειρας |
| PUT | `reject` | Άρνηση + επιστροφή credits | Μάγειρας |
| PUT | `rate` | Βαθμολογία + πληρωμή μάγειρα | Καταναλωτής |

### Stats — `backend/stats.php`

| Method | ?action= | Περιγραφή |
|---|---|---|
| GET | `leaderboard` | Top 10 μάγειρες |
| GET | `stats` | Γενικά στατιστικά |
| GET | `user-stats` | Στατιστικά χρήστη |

---

## Σύστημα Credits

| Ενέργεια | Credits |
|---|---|
| Εγγραφή | **+5** |
| Παραγγελία (qty μερίδες) | **−(κόστος × qty)** |
| Άρνηση από μάγειρα | **+(κόστος × qty)** επιστροφή |
| Βαθμολογία ≤ 3 ⭐ → Μάγειρας | **+(κόστος × qty)** |
| Βαθμολογία > 3 ⭐ → Μάγειρας | **+((κόστος + 1) × qty)** bonus |
| No-show ποινή | **−1** |

**Παράδειγμα:** Φαγητό 2 credits/μερίδα, 3 μερίδες, rating 5⭐:
- Καταναλωτής πληρώνει: **−6 credits**
- Μάγειρας παίρνει: **(2+1) × 3 = +9 credits**

Κάθε συναλλαγή εκτελείται μέσα σε **database transaction** για ασφάλεια.

---

## Κύκλος Παραγγελίας

```
Καταναλωτής παραγγέλνει
        │  credits αφαιρούνται αμέσως
        ▼
   status: pending
        │
   Μάγειρας αποφασίζει
     ┌───┴───┐
     ▼       ▼
  approve  reject
     │       │ credits επιστρέφονται
     ▼
status: approved
     │
Καταναλωτής βαθμολογεί (1-5 ⭐)
     │  μάγειρας πληρώνεται
     ▼
status: picked_up
```

---

## Βάση Δεδομένων

### Πίνακες
- **`users`** — χρήστες, ρόλοι, credits
- **`ads`** — αγγελίες φαγητού
- **`requests`** — παραγγελίες με status lifecycle

### Views
- **`active_ads`** — αγγελίες < 48ω με cook_name
- **`leaderboard`** — μάγειρες κατά πλήθος picked_up

### Stored Procedures
- **`create_request`** — παραγγελία με transaction
- **`rate_and_pay`** — βαθμολογία + πληρωμή μάγειρα
- **`handle_no_show`** — ποινή + επιστροφή μερίδας

---

## Reset δεδομένων

```
C:\xampp\htdocs\Unibite\refresh.bat
```

Διαγράφει και ξαναδημιουργεί την `unibite_db` με τα αρχικά test data.

---

## Αρχεία τεκμηρίωσης

| Αρχείο | Περιεχόμενο |
|---|---|
| [ΕΓΚΑΤΑΣΤΑΣΗ.md](ΕΓΚΑΤΑΣΤΑΣΗ.md) | Αναλυτικές οδηγίες εγκατάστασης & αντιμετώπιση προβλημάτων |
| [ΑΝΑΛΥΣΗ_PROJECT.md](ΑΝΑΛΥΣΗ_PROJECT.md) | Πλήρης τεχνική ανάλυση — DB schema, API, frontend, credits |
