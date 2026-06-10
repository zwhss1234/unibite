// ============================================================
// UniBite - Φοιτητικό Φαγητό
// Frontend JavaScript — SPA Logic
// ============================================================

const API = '../backend';

let currentUser = null;
let adsCache    = {};   // adId → ad object
let currentAds  = [];   // all ads from last feed fetch
let leafletMap  = null; // Leaflet map instance
let mapMarkers  = [];   // active map markers
let userLat     = null; // user's geolocation lat
let userLng     = null; // user's geolocation lng

// ============================================================
// INIT
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
    const stored = localStorage.getItem('unibite_user');
    if (stored) {
        currentUser = JSON.parse(stored);
        showMainApp();
    } else {
        showLoginPage();
    }

    setupAuthForms();
    setupTabs();
    setupNewAdModal();
    setupOrderModal();
    setupAdForm();
    setupLogout();
    setupProfileNavBtn();
    setupAvatarUpload();
    setupFeedControls();
});

// ============================================================
// AUTH
// ============================================================

function setupAuthForms() {
    // Toggle register ↔ login
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const target = btn.dataset.form;
            document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
            document.getElementById(`${target}-form`).classList.add('active');
        });
    });

    // Register
    document.getElementById('register-form').addEventListener('submit', async e => {
        e.preventDefault();
        const username = document.getElementById('reg-username').value.trim();
        const email    = document.getElementById('reg-email').value.trim();
        const role     = document.getElementById('reg-role').value;

        try {
            const res  = await fetch(`${API}/auth.php?action=register`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ username, email, role }),
            });
            const data = await res.json();
            if (res.ok) {
                showAuthMessage('✅ Εγγραφή επιτυχής! 5 Credits δώρο!', 'success');
                setTimeout(() => loginUser(email), 1500);
            } else {
                showAuthMessage(data.error || 'Σφάλμα εγγραφής', 'error');
            }
        } catch {
            showAuthMessage('❌ Σφάλμα σύνδεσης με τον server', 'error');
        }
    });

    // Login
    document.getElementById('login-form').addEventListener('submit', async e => {
        e.preventDefault();
        await loginUser(document.getElementById('login-email').value.trim());
    });
}

async function loginUser(email) {
    try {
        const res  = await fetch(`${API}/auth.php?action=login`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ email }),
        });
        const data = await res.json();
        if (res.ok) {
            currentUser = data.user;
            localStorage.setItem('unibite_user', JSON.stringify(currentUser));
            showMainApp();
        } else {
            showAuthMessage(data.error || 'Σφάλμα σύνδεσης', 'error');
        }
    } catch {
        showAuthMessage('❌ Σφάλμα σύνδεσης με τον server', 'error');
    }
}

function showAuthMessage(msg, type) {
    const el = document.getElementById('login-message');
    el.textContent  = msg;
    el.className    = `login-message ${type}`;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 5000);
}

function setupLogout() {
    document.getElementById('logout-btn').addEventListener('click', () => {
        fetch(`${API}/auth.php?action=logout`, { method: 'POST' }).catch(() => {});
        localStorage.removeItem('unibite_user');
        currentUser = null;
        adsCache    = {};
        showLoginPage();
    });
}

// ============================================================
// PAGE VISIBILITY
// ============================================================

function showLoginPage() {
    document.getElementById('login-page').style.display = 'flex';
    document.getElementById('main-app').classList.add('hidden');
}

function showMainApp() {
    document.getElementById('login-page').style.display = 'none';
    document.getElementById('main-app').classList.remove('hidden');

    updateCreditsDisplay(currentUser.credits);

    const initials = getInitials(currentUser.username);
    document.getElementById('header-avatar-initials').textContent = initials;
    updateAvatarDisplay(currentUser.avatar_path);

    // Εμφάνιση Admin tab μόνο για admin
    const adminTab = document.getElementById('admin-tab-btn');
    if (currentUser.role === 'admin') {
        adminTab.classList.remove('hidden');
    } else {
        adminTab.classList.add('hidden');
    }

    loadAds();
    loadRequests();
    loadLeaderboard();
}

function updateCreditsDisplay(credits) {
    document.getElementById('user-credits').textContent = credits;
    if (currentUser) {
        currentUser.credits = credits;
        localStorage.setItem('unibite_user', JSON.stringify(currentUser));
    }
}

// ============================================================
// TABS
// ============================================================

function setupTabs() {
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            const id = tab.dataset.tab;
            document.getElementById(`${id}-section`).classList.add('active');

            if (id === 'profile')    loadProfile();
            if (id === 'my-ads')     loadMyAds();
            if (id === 'requests')   loadRequests();
            if (id === 'leaderboard') loadLeaderboard();
            if (id === 'admin')      loadAdminDashboard();
        });
    });
}

function setupProfileNavBtn() {
    document.getElementById('profile-nav-btn').addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.getElementById('profile-tab-btn').classList.add('active');
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('profile-section').classList.add('active');
        loadProfile();
    });
}

// ============================================================
// FEED — ADS
// ============================================================

async function loadAds() {
    try {
        let url = `${API}/ads.php?action=feed`;
        const sort = document.getElementById('sort-select')?.value;
        if (sort === 'distance' && userLat !== null && userLng !== null) {
            url += `&lat=${userLat}&lng=${userLng}`;
        }
        const res  = await fetch(url);
        const data = await res.json();
        if (res.ok) {
            adsCache    = {};
            currentAds  = data.ads || [];
            currentAds.forEach(ad => { adsCache[ad.id] = ad; });
            renderAds(currentAds);
            updateMapMarkers(currentAds);
        }
    } catch (err) {
        console.error('loadAds:', err);
    }
}

function renderAds(ads) {
    const container = document.getElementById('ads-container');
    if (!ads.length) {
        container.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon">🍽️</span>
                <p>Δεν υπάρχουν διαθέσιμα φαγητά αυτή τη στιγμή</p>
            </div>`;
        return;
    }

    container.innerHTML = ads.map(ad => `
        <article class="food-card${ad.current_state === 'Inactive' ? ' card-inactive' : ''}" data-id="${ad.id}">
            ${ad.image_path
                ? `<img class="food-img" src="../${escHtml(ad.image_path)}" alt="${escHtml(ad.title)}">`
                : `<div class="food-image">${foodEmoji(ad.title)}</div>`
            }
            <div class="food-content">
                <span class="food-status ${ad.current_state === 'Active' ? 'active' : 'inactive'}">
                    ${ad.current_state === 'Active' ? 'Διαθέσιμο' : 'Εξαντλήθηκε'}
                </span>
                <h3>${escHtml(ad.title)}</h3>
                <p class="food-description">${escHtml(ad.description || '')}</p>
                <div class="food-meta">
                    <span>⚠️ ${escHtml(ad.allergens || 'Καμία')}</span>
                    <span>📦 ${ad.available_portions}/${ad.total_portions} μερίδες</span>
                    ${ad.distance_km != null ? `<span>📍 ${ad.distance_km.toFixed(2)} km</span>` : ''}
                </div>
                <div class="food-location">
                    <span>📍 ${escHtml(ad.pickup_location)}</span>
                    <span>🕐 ${fmtTime(ad.pickup_time)}</span>
                </div>
                <div class="food-footer">
                    <span class="price">${ad.credit_costs} 🪙/μερίδα</span>
                    <button class="btn btn-primary btn-sm"
                        onclick="openOrderModal(${ad.id})"
                        ${ad.available_portions <= 0 ? 'disabled' : ''}>
                        ${ad.available_portions > 0 ? 'Παραγγελία' : 'Εξαντλήθηκε'}
                    </button>
                </div>
            </div>
        </article>`).join('');
}

// ============================================================
// MY ADS
// ============================================================

async function loadMyAds() {
    if (!currentUser) return;
    try {
        await ensureSession();
        const res  = await fetch(`${API}/ads.php?action=my-ads`);
        const data = await res.json();
        if (res.ok) renderMyAds(data.ads || []);
    } catch (err) {
        console.error('loadMyAds:', err);
    }
}

function renderMyAds(ads) {
    const container = document.getElementById('my-ads-container');
    if (!ads.length) {
        container.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon">🍳</span>
                <p>Δεν έχεις δημοσιεύσει ακόμα φαγητό</p>
                <button class="btn btn-primary" onclick="openNewAdModal()">Δημιουργία Αγγελίας</button>
            </div>`;
        return;
    }

    container.innerHTML = ads.map(ad => `
        <div class="my-ad-card">
            <div class="my-ad-info">
                <h4>${escHtml(ad.title)}</h4>
                <p class="my-ad-meta">
                    ${ad.available_portions}/${ad.total_portions} μερίδες &middot;
                    ${ad.credit_costs} 🪙/μερίδα &middot;
                    📍 ${escHtml(ad.pickup_location)}
                </p>
            </div>
            <button class="btn btn-danger btn-sm" onclick="deleteAd(${ad.id})">🗑</button>
        </div>`).join('');
}

async function deleteAd(adId) {
    if (!confirm('Διαγραφή αγγελίας;')) return;
    try {
        await ensureSession();
        const res = await fetch(`${API}/ads.php?id=${adId}`, { method: 'DELETE' });
        const data = await res.json();
        if (res.ok) {
            showToast('🗑 Αγγελία διαγράφηκε', 'success');
            loadMyAds();
            loadAds();
        } else {
            showToast(data.error || 'Σφάλμα διαγραφής', 'error');
        }
    } catch {
        showToast('Σφάλμα σύνδεσης', 'error');
    }
}

// ============================================================
// REQUESTS
// ============================================================

async function loadRequests() {
    if (!currentUser) return;
    try {
        await ensureSession();
        const isCook = currentUser.role === 'cook';
        const action = isCook ? 'incoming' : 'my-requests';
        const res    = await fetch(`${API}/requests.php?action=${action}`);
        const data   = await res.json();

        if (res.ok) {
            document.getElementById('requests-section-title').textContent =
                isCook ? 'Εισερχόμενες Παραγγελίες' : 'Οι Παραγγελίες μου';

            if (isCook) {
                renderCookRequests(data.requests || []);
            } else {
                renderConsumerRequests(data.requests || []);
            }
        }
    } catch (err) {
        console.error('loadRequests:', err);
    }
}

function renderCookRequests(requests) {
    const container = document.getElementById('requests-container');
    if (!requests.length) {
        container.innerHTML = `<div class="empty-state"><span class="empty-icon">📋</span><p>Δεν υπάρχουν εισερχόμενες παραγγελίες</p></div>`;
        return;
    }

    container.innerHTML = requests.map(r => {
        if (r.status === 'pending') return `
            <div class="request-card pending">
                <div class="request-info">
                    <h4>${escHtml(r.title)}</h4>
                    <span class="request-meta">👤 ${escHtml(r.consumer_name)} &middot; ${r.quantity} μερίδα</span>
                    <span class="request-status pending-text">⏳ Αναμένει έγκρισή σου</span>
                </div>
                <div class="request-actions">
                    <button class="btn btn-sm btn-success" onclick="approveRequest(${r.id})">✓ Αποδοχή</button>
                    <button class="btn btn-sm btn-danger"  onclick="rejectRequest(${r.id})">✗ Άρνηση</button>
                </div>
            </div>`;

        if (r.status === 'approved') return `
            <div class="request-card approved">
                <div class="request-info">
                    <h4>${escHtml(r.title)}</h4>
                    <span class="request-meta">👤 ${escHtml(r.consumer_name)} &middot; ${r.quantity} μερίδα</span>
                    <span class="request-status approved-text">✅ Εγκρίθηκε — αναμένεται παραλαβή</span>
                </div>
            </div>`;

        if (r.status === 'picked_up') {
            const earned = calcEarned(r.credit_costs, r.quantity, r.rating);
            return `
            <div class="request-card completed">
                <div class="request-info">
                    <h4>${escHtml(r.title)}</h4>
                    <span class="request-meta">👤 ${escHtml(r.consumer_name)} &middot; ${r.quantity} μερίδα</span>
                    <span class="request-status completed-text">✅ Παραλήφθηκε ${r.rating ? '&middot; ' + '⭐'.repeat(r.rating) : ''}</span>
                </div>
                <span class="credits-earned">+${earned} 🪙</span>
            </div>`;
        }

        if (r.status === 'rejected') return `
            <div class="request-card rejected">
                <div class="request-info">
                    <h4>${escHtml(r.title)}</h4>
                    <span class="request-meta">👤 ${escHtml(r.consumer_name)}</span>
                    <span class="request-status rejected-text">❌ Απορρίφθηκε</span>
                </div>
            </div>`;

        return '';
    }).join('');
}

function renderConsumerRequests(requests) {
    const container = document.getElementById('requests-container');
    if (!requests.length) {
        container.innerHTML = `<div class="empty-state"><span class="empty-icon">📋</span><p>Δεν έχεις παραγγελίες ακόμα</p></div>`;
        return;
    }

    container.innerHTML = requests.map(r => {
        if (r.status === 'pending') return `
            <div class="request-card pending">
                <div class="request-info">
                    <h4>${escHtml(r.title)}</h4>
                    <span class="request-meta">🍳 ${escHtml(r.cook_name)}</span>
                    <span class="request-status pending-text">⏳ Αναμένει έγκριση από τον μάγειρα</span>
                </div>
                <span class="request-cost">-${r.credit_costs * r.quantity} 🪙</span>
            </div>`;

        if (r.status === 'approved') return `
            <div class="request-card approved">
                <div class="request-info">
                    <h4>${escHtml(r.title)}</h4>
                    <span class="request-meta">🍳 ${escHtml(r.cook_name)} &middot; 📍 ${escHtml(r.pickup_location)}</span>
                    <span class="request-status approved-text">✅ Εγκρίθηκε — πήγαινε να παραλάβεις!</span>
                </div>
                <div class="rating-section">
                    <p>Βαθμολόγησε μετά την παραλαβή:</p>
                    <div class="stars">
                        ${[1,2,3,4,5].map(n => `<span class="star" onclick="rateRequest(${r.id}, ${n})">⭐</span>`).join('')}
                    </div>
                </div>
            </div>`;

        if (r.status === 'picked_up') return `
            <div class="request-card completed">
                <div class="request-info">
                    <h4>${escHtml(r.title)}</h4>
                    <span class="request-meta">🍳 ${escHtml(r.cook_name)}</span>
                    <span class="request-status completed-text">✅ Ολοκληρώθηκε</span>
                </div>
                <span class="rating-display">${r.rating ? '⭐'.repeat(r.rating) : ''}</span>
            </div>`;

        if (r.status === 'rejected') return `
            <div class="request-card rejected">
                <div class="request-info">
                    <h4>${escHtml(r.title)}</h4>
                    <span class="request-meta">🍳 ${escHtml(r.cook_name)}</span>
                    <span class="request-status rejected-text">❌ Απορρίφθηκε — τα credits επιστράφηκαν</span>
                </div>
            </div>`;

        if (r.status === 'no_show') return `
            <div class="request-card rejected">
                <div class="request-info">
                    <h4>${escHtml(r.title)}</h4>
                    <span class="request-status rejected-text">👻 No-show — ποινή -1 🪙</span>
                </div>
            </div>`;

        return '';
    }).join('');
}

// ============================================================
// LEADERBOARD
// ============================================================

async function loadLeaderboard() {
    try {
        const res  = await fetch(`${API}/stats.php?action=leaderboard`);
        const data = await res.json();
        if (res.ok) renderLeaderboard(data.leaderboard || []);
    } catch (err) {
        console.error('loadLeaderboard:', err);
    }
}

function renderLeaderboard(list) {
    const container = document.getElementById('leaderboard-container');
    if (!list.length) {
        container.innerHTML = `<div class="empty-state"><span class="empty-icon">🏆</span><p>Δεν υπάρχουν δεδομένα ακόμα</p></div>`;
        return;
    }

    const [top, ...rest] = list;
    container.innerHTML = `
        <div class="top-donor">
            <span class="trophy">🥇</span>
            <div class="donor-info">
                <h3>${escHtml(top.username)}</h3>
                <p class="donor-stats">${top.total_given} μερίδες μοιράστηκαν</p>
            </div>
        </div>
        ${rest.length ? `
        <h4>Υπόλοιποι Μάγειρες</h4>
        <ol class="donor-list">
            ${rest.map(d => `
                <li>
                    <span class="donor-name">${escHtml(d.username)}</span>
                    <span class="donor-count">${d.total_given} μερίδες</span>
                </li>`).join('')}
        </ol>` : ''}`;
}

// ============================================================
// PROFILE
// ============================================================

function loadProfile() {
    if (!currentUser) return;

    document.getElementById('profile-initials').textContent  = getInitials(currentUser.username);
    document.getElementById('profile-username').textContent  = currentUser.username;
    document.getElementById('profile-email').textContent     = currentUser.email;
    document.getElementById('profile-credits').textContent   = currentUser.credits;
    updateAvatarDisplay(currentUser.avatar_path);

    const roleLabel = { cook: '🍳 Μάγειρας', consumer: '🍽️ Καταναλωτής', admin: '⚙️ Admin' };
    document.getElementById('profile-role').textContent = roleLabel[currentUser.role] || currentUser.role;

    document.getElementById('profile-since').textContent = currentUser.created_at
        ? new Date(currentUser.created_at).toLocaleDateString('el-GR')
        : '-';

    loadHistory();
}

async function loadHistory() {
    try {
        await ensureSession();
        const isCook = currentUser?.role === 'cook';
        const action = isCook ? 'cook-history' : 'history';
        const res    = await fetch(`${API}/requests.php?action=${action}`);
        const data   = await res.json();
        if (res.ok) {
            document.getElementById('history-title').textContent =
                isCook ? 'Ιστορικό Παραγγελιών που Εκπλήρωσα' : 'Ιστορικό Παραγγελιών μου';
            renderHistory(data.history || [], isCook);
        }
    } catch (err) {
        console.error('loadHistory:', err);
    }
}

function renderHistory(history, isCook) {
    const container = document.getElementById('history-list');
    if (!history.length) {
        container.innerHTML = `<div class="empty-state"><span class="empty-icon">📋</span><p>Δεν υπάρχει ιστορικό ακόμα</p></div>`;
        return;
    }

    const statusText = {
        picked_up: 'Παραλήφθηκε',
        rejected:  'Απορρίφθηκε',
        no_show:   'No-show',
    };

    container.innerHTML = history.map(item => {
        const date = fmtDate(item.received_at || item.created_at);
        const st   = statusText[item.status] || item.status;

        if (isCook) {
            const earned = item.status === 'picked_up' ? calcEarned(item.credit_costs, item.quantity, item.rating) : 0;
            return `
                <div class="history-item ${item.status}">
                    <div>
                        <div class="history-item-title">${escHtml(item.title)}</div>
                        <div class="history-item-date">👤 ${escHtml(item.consumer_name || '')} &middot; ${date}</div>
                    </div>
                    <div class="history-item-right">
                        <div class="history-item-status ${item.status}">${st}</div>
                        ${item.status === 'picked_up' ? `<div class="history-item-rating">${item.rating ? '⭐'.repeat(item.rating) : ''} +${earned} 🪙</div>` : ''}
                    </div>
                </div>`;
        }

        return `
            <div class="history-item ${item.status}">
                <div>
                    <div class="history-item-title">${escHtml(item.title)}</div>
                    <div class="history-item-date">🍳 ${escHtml(item.cook_name || '')} &middot; ${date}</div>
                </div>
                <div class="history-item-right">
                    <div class="history-item-status ${item.status}">${st}</div>
                    ${item.rating ? `<div class="history-item-rating">${'⭐'.repeat(item.rating)}</div>` : ''}
                </div>
            </div>`;
    }).join('');
}

// ============================================================
// NEW AD MODAL
// ============================================================

function setupNewAdModal() {
    const modal = document.getElementById('new-ad-modal');
    const open  = () => modal.classList.add('active');
    const close = () => modal.classList.remove('active');

    document.getElementById('new-ad-btn').addEventListener('click', open);
    document.getElementById('modal-close').addEventListener('click', close);
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
}

function openNewAdModal() {
    document.getElementById('new-ad-modal').classList.add('active');
}

function setupAdForm() {
    // Image preview
    const fileInput   = document.getElementById('ad-image');
    const previewBox  = document.getElementById('image-preview');
    const uploadText  = document.getElementById('file-upload-text');

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) {
            previewBox.classList.remove('has-image');
            previewBox.innerHTML = '';
            uploadText.textContent = 'Επίλεξε φωτογραφία...';
            return;
        }
        uploadText.textContent = file.name;
        const reader = new FileReader();
        reader.onload = e => {
            previewBox.innerHTML  = `<img src="${e.target.result}" alt="preview">`;
            previewBox.classList.add('has-image');
        };
        reader.readAsDataURL(file);
    });

    // Geolocation button
    document.getElementById('geo-btn').addEventListener('click', () => {
        if (!navigator.geolocation) { showToast('Το browser δεν υποστηρίζει geolocation', 'error'); return; }
        navigator.geolocation.getCurrentPosition(
            pos => {
                document.getElementById('ad-latitude').value  = pos.coords.latitude;
                document.getElementById('ad-longitude').value = pos.coords.longitude;
                showToast('📍 Τοποθεσία καταγράφηκε!', 'success');
                document.getElementById('geo-btn').textContent = '✅ Τοποθεσία καταγράφηκε';
            },
            () => showToast('Δεν επιτράπηκε η τοποθεσία', 'error')
        );
    });

    document.getElementById('ad-form').addEventListener('submit', async e => {
        e.preventDefault();
        await ensureSession();

        const formData = new FormData();
        formData.append('title',           document.getElementById('ad-title').value.trim());
        formData.append('description',     document.getElementById('ad-description').value.trim());
        formData.append('credit_costs',    document.getElementById('ad-credits').value);
        formData.append('total_portions',  document.getElementById('ad-portions').value);
        formData.append('allergens',       document.getElementById('ad-allergens').value.trim());
        formData.append('pickup_location', document.getElementById('ad-location').value.trim());
        formData.append('pickup_time',     document.getElementById('ad-pickup-time').value);
        const lat = document.getElementById('ad-latitude').value;
        const lng = document.getElementById('ad-longitude').value;
        if (lat) formData.append('latitude',  lat);
        if (lng) formData.append('longitude', lng);
        if (fileInput.files[0]) {
            formData.append('image', fileInput.files[0]);
        }

        try {
            const res  = await fetch(`${API}/ads.php?action=create`, {
                method: 'POST',
                body:   formData,   // χωρίς Content-Type — browser το θέτει αυτόματα με boundary
            });
            const data = await res.json();
            if (res.ok) {
                document.getElementById('new-ad-modal').classList.remove('active');
                document.getElementById('ad-form').reset();
                previewBox.classList.remove('has-image');
                previewBox.innerHTML   = '';
                uploadText.textContent = 'Επίλεξε φωτογραφία...';
                document.getElementById('ad-latitude').value  = '';
                document.getElementById('ad-longitude').value = '';
                document.getElementById('geo-btn').textContent = '📍 Χρήση τοποθεσίας μου';
                showToast('✅ Αγγελία δημοσιεύτηκε!', 'success');
                loadAds();
                loadMyAds();
            } else {
                showToast(data.error || 'Σφάλμα δημιουργίας', 'error');
            }
        } catch {
            showToast('❌ Σφάλμα σύνδεσης', 'error');
        }
    });
}

// ============================================================
// ORDER MODAL
// ============================================================

function setupOrderModal() {
    const modal      = document.getElementById('order-modal');
    const qtyEl      = document.getElementById('qty-value');
    const costEl     = document.getElementById('order-total-cost');
    const minusBtn   = document.getElementById('qty-minus');
    const plusBtn    = document.getElementById('qty-plus');
    const confirmBtn = document.getElementById('order-confirm-btn');

    const close = () => modal.classList.remove('active');

    document.getElementById('order-modal-close').addEventListener('click', close);
    document.getElementById('order-cancel-btn').addEventListener('click', close);
    modal.addEventListener('click', e => { if (e.target === modal) close(); });

    minusBtn.addEventListener('click', () => {
        const ad  = adsCache[modal.dataset.adId];
        if (!ad) return;
        const qty = Math.max(1, parseInt(qtyEl.textContent) - 1);
        qtyEl.textContent  = qty;
        costEl.textContent = `${ad.credit_costs * qty} 🪙`;
        minusBtn.disabled  = qty <= 1;
        plusBtn.disabled   = qty >= ad.available_portions;
    });

    plusBtn.addEventListener('click', () => {
        const ad  = adsCache[modal.dataset.adId];
        if (!ad) return;
        const qty = Math.min(ad.available_portions, parseInt(qtyEl.textContent) + 1);
        qtyEl.textContent  = qty;
        costEl.textContent = `${ad.credit_costs * qty} 🪙`;
        minusBtn.disabled  = qty <= 1;
        plusBtn.disabled   = qty >= ad.available_portions;
    });

    confirmBtn.addEventListener('click', async () => {
        const ad  = adsCache[modal.dataset.adId];
        const qty = parseInt(qtyEl.textContent);
        if (!ad) return;

        confirmBtn.disabled     = true;
        confirmBtn.textContent  = '⏳ ...';

        await ensureSession();

        try {
            const res  = await fetch(`${API}/requests.php?action=create`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ ad_id: ad.id, quantity: qty }),
            });
            const data = await res.json();

            if (res.ok) {
                close();
                updateCreditsDisplay(currentUser.credits - data.transaction.total_cost);
                showToast(`✅ Παραγγελία για "${ad.title}" (${qty} μερίδα) — -${data.transaction.total_cost} 🪙`, 'success');
                loadAds();
                loadRequests();
            } else {
                showToast(data.error || 'Σφάλμα παραγγελίας', 'error');
            }
        } catch {
            showToast('❌ Σφάλμα σύνδεσης', 'error');
        } finally {
            confirmBtn.disabled    = false;
            confirmBtn.textContent = '✅ Παραγγελία';
        }
    });
}

function openOrderModal(adId) {
    if (!currentUser) { showToast('Πρέπει να συνδεθείς!', 'error'); return; }

    const ad = adsCache[adId];
    if (!ad || ad.available_portions <= 0) return;

    const modal    = document.getElementById('order-modal');
    const qtyEl    = document.getElementById('qty-value');
    const costEl   = document.getElementById('order-total-cost');
    const minusBtn = document.getElementById('qty-minus');
    const plusBtn  = document.getElementById('qty-plus');

    modal.dataset.adId = adId;
    document.getElementById('order-modal-emoji').textContent = foodEmoji(ad.title);
    document.getElementById('order-modal-title').textContent = ad.title;
    document.getElementById('order-modal-desc').textContent  = ad.description || '';
    document.getElementById('qty-max-hint').textContent      = `Διαθέσιμες μερίδες: ${ad.available_portions}`;

    qtyEl.textContent  = '1';
    costEl.textContent = `${ad.credit_costs} 🪙`;
    minusBtn.disabled  = true;
    plusBtn.disabled   = ad.available_portions <= 1;

    modal.classList.add('active');
}

// ============================================================
// REQUEST ACTIONS
// ============================================================

async function approveRequest(requestId) {
    await ensureSession();
    try {
        const res  = await fetch(`${API}/requests.php?action=approve`, {
            method:  'PUT',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ request_id: requestId }),
        });
        const data = await res.json();
        if (res.ok) {
            showToast('✅ Η παραγγελία εγκρίθηκε!', 'success');
            loadRequests();
        } else {
            showToast(data.error || 'Σφάλμα έγκρισης', 'error');
        }
    } catch {
        showToast('Σφάλμα σύνδεσης', 'error');
    }
}

async function rejectRequest(requestId) {
    await ensureSession();
    try {
        const res  = await fetch(`${API}/requests.php?action=reject`, {
            method:  'PUT',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ request_id: requestId }),
        });
        const data = await res.json();
        if (res.ok) {
            showToast('❌ Η παραγγελία απορρίφθηκε', 'success');
            loadRequests();
        } else {
            showToast(data.error || 'Σφάλμα απόρριψης', 'error');
        }
    } catch {
        showToast('Σφάλμα σύνδεσης', 'error');
    }
}

async function rateRequest(requestId, rating) {
    await ensureSession();
    try {
        const res  = await fetch(`${API}/requests.php?action=rate`, {
            method:  'PUT',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ request_id: requestId, rating }),
        });
        const data = await res.json();
        if (res.ok) {
            showToast(`⭐ Βαθμολόγησες με ${rating} αστέρια! Ο μάγειρας πήρε +${data.reward} 🪙`, 'success');
            loadRequests();
        } else {
            showToast(data.error || 'Σφάλμα βαθμολογίας', 'error');
        }
    } catch {
        showToast('Σφάλμα σύνδεσης', 'error');
    }
}

// ============================================================
// SESSION HELPER
// ============================================================

async function ensureSession() {
    try {
        const res = await fetch(`${API}/auth.php?action=me`);
        if (res.status === 401 && currentUser) {
            await fetch(`${API}/auth.php?action=login`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ email: currentUser.email }),
            });
        }
    } catch (_) {}
}

// ============================================================
// TOAST
// ============================================================

function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className   = `toast ${type}`;
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(() => {
        requestAnimationFrame(() => el.classList.add('show'));
    });
    setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 400);
    }, 3500);
}

// ============================================================
// HELPERS
// ============================================================

function calcEarned(creditCosts, quantity, rating) {
    const c = parseInt(creditCosts);
    const q = parseInt(quantity);
    const r = parseInt(rating);
    return r > 3 ? (c + 1) * q : c * q;
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(/[_\s]/).map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

function fmtTime(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('el-GR', { hour: '2-digit', minute: '2-digit' });
}

function fmtDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('el-GR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str)));
    return d.innerHTML;
}

function foodEmoji(title) {
    if (!title) return '🍽️';
    const t = title.toLowerCase();
    if (t.includes('παστίτσιο') || t.includes('μακαρον')) return '🍝';
    if (t.includes('σαλάτα'))                              return '🥗';
    if (t.includes('μουσακάς') || t.includes('παπά'))     return '🥘';
    if (t.includes('πίτσα'))                               return '🍕';
    if (t.includes('σουβλάκι'))                            return '🥙';
    if (t.includes('γλυκό')  || t.includes('τούρτα'))     return '🍰';
    if (t.includes('κοτόπουλο'))                           return '🍗';
    if (t.includes('ψάρι')   || t.includes('σολομ'))      return '🐟';
    if (t.includes('σπανακό'))                             return '🥬';
    if (t.includes('γεμιστ'))                              return '🍅';
    return '🍽️';
}

// ============================================================
// FEED CONTROLS — view toggle, sort by distance
// ============================================================

function setupFeedControls() {
    document.getElementById('list-view-btn').addEventListener('click', () => switchFeedView('list'));
    document.getElementById('map-view-btn').addEventListener('click',  () => switchFeedView('map'));

    document.getElementById('sort-select').addEventListener('change', async () => {
        const sort = document.getElementById('sort-select').value;
        if (sort === 'distance') {
            if (!navigator.geolocation) {
                showToast('Το browser δεν υποστηρίζει geolocation', 'error');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                pos => {
                    userLat = pos.coords.latitude;
                    userLng = pos.coords.longitude;
                    loadAds();
                },
                () => showToast('Δεν επιτράπηκε πρόσβαση στην τοποθεσία', 'error')
            );
        } else {
            loadAds();
        }
    });
}

function switchFeedView(view) {
    const listEl  = document.getElementById('ads-container');
    const mapEl   = document.getElementById('map-container');
    const listBtn = document.getElementById('list-view-btn');
    const mapBtn  = document.getElementById('map-view-btn');

    if (view === 'list') {
        listEl.style.display = '';
        mapEl.style.display  = 'none';
        listBtn.classList.add('active');
        mapBtn.classList.remove('active');
    } else {
        listEl.style.display = 'none';
        mapEl.style.display  = 'block';
        mapBtn.classList.add('active');
        listBtn.classList.remove('active');
        initLeafletMap();
    }
}

// ============================================================
// MAP — Leaflet.js
// ============================================================

function initLeafletMap() {
    if (leafletMap) {
        leafletMap.invalidateSize();
        updateMapMarkers(currentAds);
        return;
    }

    // Default center: University of Athens area
    const center = [37.9788, 23.7386];
    leafletMap = L.map('map-container').setView(center, 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(leafletMap);

    updateMapMarkers(currentAds);
}

function updateMapMarkers(ads) {
    if (!leafletMap) return;

    mapMarkers.forEach(m => m.remove());
    mapMarkers = [];

    const withCoords = ads.filter(ad => ad.latitude && ad.longitude);

    withCoords.forEach(ad => {
        const isActive = ad.current_state === 'Active';
        const icon = L.divIcon({
            html:      `<div class="map-marker${isActive ? '' : ' map-marker-inactive'}">${ad.credit_costs}🪙</div>`,
            className: '',
            iconSize:  [64, 28],
            iconAnchor:[32, 28],
        });

        const marker = L.marker([parseFloat(ad.latitude), parseFloat(ad.longitude)], { icon })
            .addTo(leafletMap)
            .bindPopup(`
                <strong>${escHtml(ad.title)}</strong><br>
                🍳 ${escHtml(ad.cook_name)}<br>
                📦 ${ad.available_portions} μερίδες &middot; ${ad.credit_costs} 🪙/μερίδα<br>
                📍 ${escHtml(ad.pickup_location)}
                ${isActive
                    ? `<br><br><button onclick="openOrderModal(${ad.id})" style="background:var(--primary);color:white;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-weight:600;">Παραγγελία</button>`
                    : '<br><span style="color:#e74c3c;font-weight:600;">Εξαντλήθηκε</span>'
                }
            `);

        mapMarkers.push(marker);
    });
}

// ============================================================
// ADMIN DASHBOARD
// ============================================================

async function loadAdminDashboard() {
    try {
        await ensureSession();
        const res  = await fetch(`${API}/stats.php?action=admin-stats`);
        const data = await res.json();
        if (!res.ok) { showToast(data.error || 'Σφάλμα φόρτωσης', 'error'); return; }
        renderAdminDashboard(data);
    } catch (err) {
        console.error('loadAdminDashboard:', err);
    }
}

function renderAdminDashboard(d) {
    document.getElementById('admin-portions').textContent   = d.portions_last_month ?? 0;
    document.getElementById('admin-users').textContent      = d.total_users ?? 0;
    document.getElementById('admin-active-ads').textContent = d.active_ads ?? 0;

    // Top Donor
    const donorEl = document.getElementById('admin-top-donor');
    if (d.top_donor) {
        donorEl.innerHTML = `
            <div class="admin-top-donor-info">
                <div class="admin-donor-avatar">${escHtml(getInitials(d.top_donor.username))}</div>
                <div>
                    <div class="admin-donor-name">${escHtml(d.top_donor.username)}</div>
                    <div class="admin-donor-stat">${d.top_donor.portions_given} μερίδες σε σύνολο</div>
                </div>
            </div>`;
    } else {
        donorEl.textContent = 'Δεν υπάρχουν δεδομένα ακόμα';
    }

    // Top Rated Meals
    const ratedEl = document.getElementById('admin-top-rated');
    if (d.top_rated_meals?.length) {
        ratedEl.innerHTML = `<div class="top-rated-list">
            ${d.top_rated_meals.map((m, i) => `
                <div class="top-rated-item">
                    <div>
                        <div class="top-rated-title">${i + 1}. ${escHtml(m.title)}</div>
                        <div class="top-rated-cook">🍳 ${escHtml(m.cook_name)} &middot; ${m.review_count} αξιολογήσεις</div>
                    </div>
                    <div class="top-rated-stars">★ ${m.avg_rating}</div>
                </div>`).join('')}
        </div>`;
    } else {
        ratedEl.textContent = 'Δεν υπάρχουν αξιολογήσεις ακόμα';
    }

    // Monthly chart
    const monthlyEl = document.getElementById('admin-monthly');
    if (d.monthly_portions?.length) {
        const maxVal = Math.max(...d.monthly_portions.map(m => parseInt(m.portions)), 1);
        monthlyEl.innerHTML = `<div class="monthly-bars">
            ${d.monthly_portions.map(m => {
                const pct = Math.round((parseInt(m.portions) / maxVal) * 100);
                return `
                <div class="monthly-row">
                    <span class="monthly-label">${m.month.slice(5)}</span>
                    <div class="monthly-bar-wrap"><div class="monthly-bar" style="width:${pct}%"></div></div>
                    <span class="monthly-val">${m.portions}</span>
                </div>`;
            }).join('')}
        </div>`;
    } else {
        monthlyEl.textContent = 'Δεν υπάρχουν δεδομένα ακόμα';
    }
}

// ============================================================
// AVATAR UPLOAD
// ============================================================

function setupAvatarUpload() {
    const input = document.getElementById('avatar-file-input');
    if (!input) return;
    input.addEventListener('change', async () => {
        const file = input.files[0];
        if (!file) return;
        await ensureSession();
        const fd = new FormData();
        fd.append('avatar', file);
        try {
            const res  = await fetch(`${API}/auth.php?action=upload-avatar`, { method: 'POST', body: fd });
            const data = await res.json();
            if (res.ok) {
                currentUser.avatar_path = data.avatar_path;
                localStorage.setItem('unibite_user', JSON.stringify(currentUser));
                updateAvatarDisplay(data.avatar_path);
                showToast('✅ Φωτογραφία προφίλ ενημερώθηκε!', 'success');
            } else {
                showToast(data.error || 'Σφάλμα ανεβάσματος', 'error');
            }
        } catch {
            showToast('❌ Σφάλμα σύνδεσης', 'error');
        }
        input.value = '';
    });
}

function updateAvatarDisplay(avatarPath) {
    const headerImg       = document.getElementById('header-avatar-img');
    const headerInitials  = document.getElementById('header-avatar-initials');
    const profileImg      = document.getElementById('profile-avatar-img');
    const profileInitials = document.getElementById('profile-initials');

    if (avatarPath) {
        const src = `../${avatarPath}`;
        if (headerImg)       { headerImg.src = src; headerImg.style.display = 'block'; }
        if (headerInitials)  headerInitials.style.display = 'none';
        if (profileImg)      { profileImg.src = src; profileImg.style.display = 'block'; }
        if (profileInitials) profileInitials.style.display = 'none';
    } else {
        if (headerImg)       headerImg.style.display = 'none';
        if (headerInitials)  headerInitials.style.display = '';
        if (profileImg)      profileImg.style.display = 'none';
        if (profileInitials) profileInitials.style.display = '';
    }
}

// ============================================================
// GLOBAL EXPORTS (for inline onclick handlers)
// ============================================================
window.openOrderModal       = openOrderModal;
window.openNewAdModal       = openNewAdModal;
window.deleteAd             = deleteAd;
window.approveRequest       = approveRequest;
window.rejectRequest        = rejectRequest;
window.rateRequest          = rateRequest;
window.loadAdminDashboard   = loadAdminDashboard;
