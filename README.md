# 🍎 UniBite — Φοιτητικό Σύστημα Ανταλλαγής Φαγητού

> Μια web εφαρμογή που συνδέει φοιτητές-μάγειρες με φοιτητές-καταναλωτές μέσω ενός συστήματος virtual credits.

---

## Περιεχόμενα

- [Σχετικά](#σχετικά)
- [Χαρακτηριστικά](#χαρακτηριστικά)
- [Τεχνολογίες](#τεχνολογίες)
- [Εγκατάσταση](#εγκατάσταση)
- [Χρήση](#χρήση)
- [Δομή Project](#δομή-project)
- [API Endpoints](#api-endpoints)
- [Σύστημα Credits](#σύστημα-credits)
- [Κύκλος Παραγγελίας](#κύκλος-παραγγελίας)

---

## Σχετικά

Το **UniBite** είναι μια Single Page Application (SPA) που επιτρέπει σε φοιτητές να μοιράζονται σπιτικό φαγητό μέσα στο πανεπιστήμιο. Αντί για χρήματα, οι συναλλαγές γίνονται με **Credits** — ένα εικονικό νόμισμα που κερδίζεται μαγειρεύοντας και ξοδεύεται παραγγέλνοντας.

**Πρόβλημα που λύνει:** Φοιτητές που μαγειρεύουν μεγάλες ποσότητες συχνά πετούν φαγητό. Ταυτόχρονα, άλλοι φοιτητές δεν έχουν χρόνο ή γνώσεις για μαγείρεμα. Το UniBite τους συνδέει.

---

## Χαρακτηριστικά

### Για Μάγειρες 🍳
- Δημοσίευση αγγελιών φαγητού με τίτλο, περιγραφή, μερίδες, κόστος σε credits, αλλεργιογόνα και τοποθεσία παραλαβής
- Εισερχόμενες παραγγελίες με δυνατότητα **Αποδοχής** ή **Άρνησης**
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
- Εγγραφή με 5 δωρεάν credits
- **Leaderboard** με τους top μάγειρες (κατά μερίδες που έδωσαν)
- Προφίλ χρήστη με ιστορικό παραγγελιών
- Αυτόματη λήξη αγγελιών μετά από 48 ώρες
- Toast notifications (όχι browser alerts)
- Σύνδεση μόνο με email — χωρίς password

---

## Τεχνολογίες

| Επίπεδο | Τεχνολογία |
|---|---|
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Web Server | Apache 2.4 (via XAMPP) |
| Backend | PHP 8.2 |
| Database Driver | PDO (PHP Data Objects) |
| Database | MariaDB 10.4 (via XAMPP) |

**Εξωτερικές εξαρτήσεις:** μόνο Google Fonts (Poppins) — χρειάζεται σύνδεση internet για τα γραμματοσειρά.

Κανένα framework — ούτε στο frontend (React/Vue) ούτε στο backend (Laravel/Symfony). Plain PHP + Vanilla JS.

---

## Εγκατάσταση

### Προαπαιτούμενα
- Windows 10 ή 11
- [XAMPP](https://www.apachefriends.org) (περιλαμβάνει Apache + MariaDB + PHP)

### Βήματα

**1. Εγκατάσταση XAMPP**

Κατέβασε και εγκατέστησε το XAMPP από το [apachefriends.org](https://www.apachefriends.org). Ο φάκελος εγκατάστασης πρέπει να είναι `C:\xampp`.

**2. Τοποθέτηση αρχείων**

Αντίγραψε τον φάκελο `Unibite` στο:
```
C:\xampp\htdocs\Unibite\
```

**3. Εκκίνηση XAMPP**

Άνοιξε το XAMPP Control Panel και πάτα **Start** δίπλα στο **Apache** και **MySQL**.

**4. Εισαγωγή βάσης δεδομένων** *(μόνο την πρώτη φορά)*

Άνοιξε Command Prompt και τρέξε:
```
chcp 65001
C:\xampp\mysql\bin\mysql.exe -u root < "C:\xampp\htdocs\Unibite\backend\unibite.sql"
```

**5. Άνοιξε την εφαρμογή**

Πήγαινε στον browser:
```
http://localhost/Unibite/
```

> ⚠️ **Σημαντικό:** Μην ανοίγεις το `index.html` με double-click. Η εφαρμογή χρειάζεται τον Apache για να τρέξει το PHP backend.

### Αντιμετώπιση Προβλημάτων

**Το MySQL δεν ξεκινά (conflict με άλλη MySQL):**
1. XAMPP Control Panel → MySQL → Config → `my.ini`
2. Άλλαξε `port=3306` σε `port=3307`
3. Στο `backend/config.php` άλλαξε `$port = 3306;` σε `$port = 3307;`
4. Restart MySQL

**"Σφάλμα σύνδεσης με τον server":**
- Βεβαιώσου ότι Apache και MySQL τρέχουν (πράσινα στο XAMPP)
- Βεβαιώσου ότι ανοίγεις `http://localhost/Unibite/` και όχι `file://`

---

## Χρήση

### Έτοιμοι λογαριασμοί για δοκιμή

| Email | Ρόλος |
|---|---|
| `marios@uni.gr` | Μάγειρας |
| `elena@uni.gr` | Μάγειρας |
| `giorgos@uni.gr` | Μάγειρας |
| `anna@uni.gr` | Μάγειρας |
| `katerina@uni.gr` | Καταναλωτής |
| `nikos@uni.gr` | Καταναλωτής |

Σύνδεση μόνο με email — χωρίς password.

### Ως Καταναλωτής
1. **Feed** → βλέπεις τα διαθέσιμα φαγητά
2. Πάτα **Παραγγελία** → επίλεξε μερίδες → **Επιβεβαίωση**
3. Τα credits αφαιρούνται αμέσως
4. **Αιτήματα** → βλέπεις κατάσταση παραγγελίας
5. Μόλις εγκριθεί → **βαθμολόγησε** με 1-5 ⭐ για να ολοκληρωθεί

### Ως Μάγειρας
1. **Αγγελίες** → δημιούργησε νέα αγγελία φαγητού
2. **Αιτήματα** → βλέπεις εισερχόμενες παραγγελίες → **Αποδοχή** ή **Άρνηση**
3. Τα credits μπαίνουν μόλις ο καταναλωτής βαθμολογήσει

---

## Δομή Project

```
Unibite/
├── index.html              ← SPA: login + εφαρμογή σε ένα αρχείο HTML
├── style.css               ← CSS variables, components, modals, toasts
├── script.js               ← Όλη η λογική frontend (~795 γραμμές)
├── README.md
├── ΟΔΗΓΙΕΣ.md             ← Λεπτομερείς οδηγίες εκτέλεσης
├── ΕΓΚΑΤΑΣΤΑΣΗ.md         ← Εγκατάσταση από μηδέν
├── ΑΝΑΛΥΣΗ_PROJECT.md     ← Πλήρης τεχνική ανάλυση
└── backend/
    ├── config.php          ← DB connection + helper functions
    ├── auth.php            ← register / login / logout / me
    ├── ads.php             ← feed / my-ads / create / update / delete
    ├── requests.php        ← create / approve / reject / rate / history
    ├── stats.php           ← leaderboard / stats / user-stats
    └── unibite.sql         ← Schema + indexes + views + procedures + test data
```

---

## API Endpoints

Όλα τα endpoints επικοινωνούν με JSON (`Content-Type: application/json`).

### Auth — `backend/auth.php`

| Method | Action | Περιγραφή | Auth |
|---|---|---|---|
| POST | `?action=register` | Εγγραφή νέου χρήστη (+5 credits) | — |
| POST | `?action=login` | Σύνδεση με email | — |
| POST | `?action=logout` | Αποσύνδεση | — |
| GET | `?action=me` | Στοιχεία τρέχοντος χρήστη | Ναι |

### Ads — `backend/ads.php`

| Method | Action | Περιγραφή | Auth |
|---|---|---|---|
| GET | `?action=feed` | Ενεργές αγγελίες (48ω) | — |
| GET | `?action=my-ads` | Αγγελίες του χρήστη | Ναι |
| GET | `?action=view&id=X` | Μια αγγελία | — |
| POST | `?action=create` | Νέα αγγελία | Ναι |
| PUT | — | Ενημέρωση αγγελίας | Ναι |
| DELETE | `?id=X` | Διαγραφή αγγελίας | Ναι |

### Requests — `backend/requests.php`

| Method | Action | Περιγραφή | Για ποιον |
|---|---|---|---|
| GET | `?action=my-requests` | Παραγγελίες καταναλωτή | Καταναλωτής |
| GET | `?action=incoming` | Εισερχόμενες παραγγελίες | Μάγειρας |
| GET | `?action=history` | Ολοκληρωμένες (καταναλωτής) | Καταναλωτής |
| GET | `?action=cook-history` | Ολοκληρωμένες (μάγειρας) | Μάγειρας |
| POST | `?action=create` | Νέα παραγγελία | Καταναλωτής |
| PUT | `?action=approve` | Έγκριση παραγγελίας | Μάγειρας |
| PUT | `?action=reject` | Άρνηση παραγγελίας | Μάγειρας |
| PUT | `?action=rate` | Βαθμολογία + πληρωμή μάγειρα | Καταναλωτής |

### Stats — `backend/stats.php`

| Method | Action | Περιγραφή |
|---|---|---|
| GET | `?action=leaderboard` | Top 10 μάγειρες |
| GET | `?action=stats` | Γενικά στατιστικά |
| GET | `?action=user-stats` | Στατιστικά χρήστη |

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

**Παράδειγμα:** Φαγητό 2 credits/μερίδα, παραγγελία 3 μερίδες, rating 5⭐:
- Καταναλωτής πληρώνει: **−6 credits**
- Μάγειρας παίρνει: **(2+1) × 3 = +9 credits**

Όλες οι συναλλαγές γίνονται μέσα σε **database transactions** (begin/commit/rollback) για να εξασφαλιστεί ότι δεν χάνονται credits αν κάτι πάει λάθος στη μέση.

---

## Κύκλος Παραγγελίας

```
Καταναλωτής παραγγέλνει
        ↓
  status: pending
  (credits αφαιρούνται αμέσως)
        ↓
  Μάγειρας αποφασίζει
     ↙         ↘
 Αποδοχή      Άρνηση
     ↓             ↓
status: approved   status: rejected
     ↓             (credits επιστρέφονται)
Καταναλωτής βαθμολογεί (1-5 ⭐)
     ↓
status: picked_up
(μάγειρας πληρώνεται)
```

---

## Βάση Δεδομένων

### Πίνακες

```sql
users     — id, username, email, role (cook/consumer/admin), credits, created_at
ads       — id, cook_id, title, credit_costs, description, total_portions,
            available_portions, allergens, pickup_location, pickup_time, created_at
requests  — id, ad_id, consumer_id, quantity, status, rating, received_at, created_at
```

### Views & Stored Procedures

Το `unibite.sql` περιλαμβάνει:
- **View** `active_ads` — αγγελίες < 48 ώρες με cook_name
- **View** `leaderboard` — μάγειρες ταξινομημένοι κατά picked_up
- **Procedure** `create_request` — δημιουργία παραγγελίας με transaction
- **Procedure** `rate_and_pay` — βαθμολογία + πληρωμή μάγειρα
- **Procedure** `handle_no_show` — ποινή no-show + επιστροφή μερίδας

---

*Αναπτύχθηκε ως project φοιτητικής εφαρμογής — PHP/MariaDB/Vanilla JS, χωρίς εξωτερικά frameworks.*
