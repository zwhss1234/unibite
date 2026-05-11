// ========================================
// UniBite - Φοιτητικό Φαγητό
// JavaScript
// ========================================

// --- API Configuration ---
const API_BASE = '../backend';

// --- State ---
let currentUser = null;
let adsCache = {}; // adId -> ad object

// --- DOM Elements ---
document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

function initApp() {
    const storedUser = localStorage.getItem('unibite_user');
    if (storedUser) {
        currentUser = JSON.parse(storedUser);
        showMainApp();
    } else {
        showLoginPage();
    }

    setupAuthForms();
    setupTabs();
    setupModal();
    setupOrderModal();
    setupAdForm();
    setupLogout();
    setupProfileNavBtn();
}

// --- Auth Forms ---
function setupAuthForms() {
    // Toggle between register/login
    const toggleBtns = document.querySelectorAll('.toggle-btn');
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            toggleBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const form = btn.dataset.form;
            document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
            document.getElementById(`${form}-form`).classList.add('active');
        });
    });
    
    // Register form
    const registerForm = document.getElementById('register-form');
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const username = document.getElementById('reg-username').value;
        const email = document.getElementById('reg-email').value;
        const role = document.getElementById('reg-role').value;
        
        try {
            const response = await fetch(`${API_BASE}/auth.php?action=register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, email, role })
            });
            
            const data = await response.json();
            
            if (response.ok) {
                showMessage('✅ Εγγραφή επιτυχής! 5 Credits δώρο!', 'success');
                // Auto login after register
                setTimeout(() => loginUser(email), 1500);
            } else {
                showMessage(data.error || 'Σφάλμα εγγραφής', 'error');
            }
        } catch (error) {
            showMessage('❌ Σφάλμα σύνδεσης με τον server', 'error');
        }
    });
    
    // Login form
    const loginForm = document.getElementById('login-form');
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const email = document.getElementById('login-email').value;
        await loginUser(email);
    });
}

async function loginUser(email) {
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            currentUser = data.user;
            localStorage.setItem('unibite_user', JSON.stringify(currentUser));
            showMainApp();
        } else {
            showMessage(data.error || 'Σφάλμα σύνδεσης', 'error');
        }
    } catch (error) {
        showMessage('❌ Σφάλμα σύνδεσης με τον server', 'error');
    }
}

function showMessage(message, type) {
    const msgEl = document.getElementById('login-message');
    msgEl.textContent = message;
    msgEl.className = `login-message ${type}`;
    msgEl.style.display = 'block';
    
    setTimeout(() => {
        msgEl.style.display = 'none';
    }, 5000);
}

function showLoginPage() {
    document.getElementById('login-page').style.display = 'flex';
    document.getElementById('main-app').classList.add('hidden');
}

function showMainApp() {
    document.getElementById('login-page').style.display = 'none';
    document.getElementById('main-app').classList.remove('hidden');

    document.getElementById('user-credits').textContent = currentUser.credits;

    const initials = getInitials(currentUser.username);
    document.getElementById('header-avatar-initials').textContent = initials;

    loadAds();
    loadRequests();
    loadLeaderboard();
}

function setupLogout() {
    document.getElementById('logout-btn').addEventListener('click', () => {
        localStorage.removeItem('unibite_user');
        currentUser = null;
        showLoginPage();
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

function loadProfile() {
    if (!currentUser) return;

    const initials = getInitials(currentUser.username);
    document.getElementById('profile-initials').textContent = initials;
    document.getElementById('profile-username').textContent = currentUser.username;
    document.getElementById('profile-email').textContent = currentUser.email;
    document.getElementById('profile-credits').textContent = currentUser.credits;

    const roleLabels = { cook: '🍳 Μάγειρας', consumer: '🍽️ Καταναλωτής', admin: '⚙️ Admin' };
    document.getElementById('profile-role').textContent = roleLabels[currentUser.role] || currentUser.role;

    const since = currentUser.created_at
        ? new Date(currentUser.created_at).toLocaleDateString('el-GR')
        : '-';
    document.getElementById('profile-since').textContent = since;

    loadHistory();
}

async function loadHistory() {
    try {
        const isCook = currentUser?.role === 'cook';
        const action = isCook ? 'cook-history' : 'history';
        const response = await fetch(`${API_BASE}/requests.php?action=${action}`);
        const data = await response.json();

        if (response.ok) {
            const titleEl = document.querySelector('.profile-section-title');
            titleEl.textContent = isCook ? 'Ιστορικό Παραγγελιών που Εκπλήρωσα' : 'Ιστορικό Παραγγελιών μου';
            renderHistory(data.history || [], isCook);
        }
    } catch (error) {
        console.error('Error loading history:', error);
    }
}

function renderHistory(history, isCook) {
    const container = document.getElementById('history-list');

    if (!history || history.length === 0) {
        container.innerHTML = `<div class="empty-state"><span class="empty-icon">📋</span><p>Δεν υπάρχει ιστορικό ακόμα</p></div>`;
        return;
    }

    container.innerHTML = history.map(item => {
        const date = formatDate(item.received_at || item.created_at);

        if (isCook) {
            const earned = item.status === 'picked_up'
                ? (item.rating > 3
                    ? (parseInt(item.credit_costs) + 1) * parseInt(item.quantity)
                    : parseInt(item.credit_costs) * parseInt(item.quantity))
                : 0;
            const statusText = { picked_up: 'Παραλήφθηκε', rejected: 'Απορρίφθηκε', no_show: 'No-show' }[item.status] || item.status;
            return `
                <div class="history-item ${item.status}">
                    <div>
                        <div class="history-item-title">${item.title}</div>
                        <div class="history-item-date">👤 ${item.consumer_name} &middot; ${date}</div>
                    </div>
                    <div class="history-item-right">
                        <div class="history-item-status ${item.status}">${statusText}</div>
                        ${item.status === 'picked_up' ? `<div class="history-item-rating">${item.rating ? '⭐'.repeat(item.rating) : ''} +${earned} 🪙</div>` : ''}
                    </div>
                </div>`;
        } else {
            const statusText = { picked_up: 'Παραλήφθηκε', no_show: 'No-show', rejected: 'Απορρίφθηκε' }[item.status] || item.status;
            return `
                <div class="history-item ${item.status}">
                    <div>
                        <div class="history-item-title">${item.title}</div>
                        <div class="history-item-date">🍳 ${item.cook_name || ''} &middot; ${date}</div>
                    </div>
                    <div class="history-item-right">
                        <div class="history-item-status ${item.status}">${statusText}</div>
                        ${item.rating ? `<div class="history-item-rating">${'⭐'.repeat(item.rating)}</div>` : ''}
                    </div>
                </div>`;
        }
    }).join('');
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(/[_\s]/).map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

function formatDate(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('el-GR', { day: '2-digit', month: 'short', year: 'numeric' });
}

// --- Tab Navigation ---
function setupTabs() {
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            tabContents.forEach(content => content.classList.remove('active'));
            const tabId = tab.dataset.tab;
            document.getElementById(`${tabId}-section`).classList.add('active');

            if (tabId === 'profile') loadProfile();
        });
    });
}

// --- Modal Functions ---
function setupModal() {
    const newAdBtn = document.getElementById('new-ad-btn');
    const createAdBtn = document.getElementById('create-ad-btn');
    const modal = document.getElementById('new-ad-modal');
    const modalClose = document.getElementById('modal-close');

    const openModal = () => modal.classList.add('active');
    const closeModal = () => modal.classList.remove('active');

    if (newAdBtn) newAdBtn.addEventListener('click', openModal);
    if (createAdBtn) createAdBtn.addEventListener('click', openModal);
    if (modalClose) modalClose.addEventListener('click', closeModal);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
}

// --- Ad Form ---
function setupAdForm() {
    const form = document.getElementById('ad-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const adData = {
            title: document.getElementById('title').value,
            description: document.getElementById('description').value,
            credit_costs: parseInt(document.getElementById('credits').value),
            total_portions: parseInt(document.getElementById('portions').value),
            allergens: document.getElementById('allergens').value,
            pickup_location: document.getElementById('location').value,
            pickup_time: document.getElementById('pickup-time').value
        };

        try {
            const response = await fetch(`${API_BASE}/ads.php?action=create`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(adData)
            });

            const data = await response.json();

            if (response.ok) {
                document.getElementById('new-ad-modal').classList.remove('active');
                form.reset();
                alert('✅ Η αγγελία δημοσιεύτηκε επιτυχώς!');
                loadAds();
            } else {
                alert(data.error || 'Σφάλμα δημιουργίας αγγελίας');
            }
        } catch (error) {
            alert('❌ Σφάλμα σύνδεσης');
        }
    });
}

// --- API Functions ---
async function loadAds() {
    try {
        const response = await fetch(`${API_BASE}/ads.php?action=feed`);
        const data = await response.json();

        if (response.ok) {
            adsCache = {};
            (data.ads || []).forEach(ad => { adsCache[ad.id] = ad; });
            renderAds(data.ads);
        }
    } catch (error) {
        console.error('Error loading ads:', error);
    }
}

async function loadRequests() {
    try {
        await ensureSession();
        const isCook = currentUser?.role === 'cook';
        const action = isCook ? 'incoming' : 'my-requests';
        const response = await fetch(`${API_BASE}/requests.php?action=${action}`);
        const data = await response.json();

        if (response.ok) {
            const titleEl = document.getElementById('requests-section-title');
            if (isCook) {
                titleEl.textContent = 'Εισερχόμενες Παραγγελίες';
                renderCookRequests(data.requests || []);
            } else {
                titleEl.textContent = 'Οι Παραγγελίες μου';
                renderConsumerRequests(data.requests || []);
            }
        }
    } catch (error) {
        console.error('Error loading requests:', error);
    }
}

async function loadLeaderboard() {
    try {
        const response = await fetch(`${API_BASE}/stats.php?action=leaderboard`);
        const data = await response.json();
        
        if (response.ok) {
            renderLeaderboard(data.leaderboard || []);
        }
    } catch (error) {
        console.error('Error loading leaderboard:', error);
    }
}

// --- Render Functions ---
function renderAds(ads) {
    const container = document.getElementById('ads-container');
    if (!container) return;

    if (!ads || ads.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon">🍽️</span>
                <p>Δεν υπάρχουν διαθέσιμα φαγητά αυτή τη στιγμή</p>
            </div>
        `;
        return;
    }

    container.innerHTML = ads.map(ad => `
        <article class="food-card" data-id="${ad.id}">
            <div class="food-image">${getFoodEmoji(ad.title)}</div>
            <div class="food-content">
                <span class="food-status ${ad.current_state === 'Active' ? 'active' : 'inactive'}">
                    ${ad.current_state === 'Active' ? 'Διαθέσιμο' : 'Εξαντλήθηκε'}
                </span>
                <h3>${ad.title}</h3>
                <p class="food-description">${ad.description || ''}</p>
                <div class="food-meta">
                    <span class="allergens">⚠️ ${ad.allergens || 'Κανένα'}</span>
                    <span class="portions">📦 ${ad.available_portions}/${ad.total_portions} μερίδες</span>
                </div>
                <div class="food-location">
                    <span>📍 ${ad.pickup_location}</span>
                    <span>🕐 ${formatTime(ad.pickup_time)}</span>
                </div>
                <div class="food-footer">
                    <span class="price">${ad.credit_costs} 🪙/μερίδα</span>
                    <button class="btn btn-primary" onclick="orderFood(${ad.id})">
                        ${ad.available_portions > 0 ? 'Παραγγελία' : 'Εξαντλήθηκε'}
                    </button>
                </div>
            </div>
        </article>
    `).join('');
}

function renderCookRequests(requests) {
    const container = document.querySelector('.requests-list');
    if (!container) return;

    if (!requests || requests.length === 0) {
        container.innerHTML = `<div class="empty-state"><span class="empty-icon">📋</span><p>Δεν υπάρχουν εισερχόμενες παραγγελίες</p></div>`;
        return;
    }

    container.innerHTML = requests.map(r => {
        if (r.status === 'pending') return `
            <div class="request-card pending">
                <div class="request-info">
                    <h4>${r.title}</h4>
                    <span class="request-meta">👤 ${r.consumer_name} &middot; ${r.quantity} μερίδα</span>
                    <span class="request-status pending-text">⏳ Αναμένει έγκρισή σου</span>
                </div>
                <div class="request-actions">
                    <button class="btn btn-sm btn-success" onclick="approveRequest(${r.id})">✓ Αποδοχή</button>
                    <button class="btn btn-sm btn-danger" onclick="rejectRequest(${r.id})">✗ Άρνηση</button>
                </div>
            </div>`;

        if (r.status === 'approved') return `
            <div class="request-card approved">
                <div class="request-info">
                    <h4>${r.title}</h4>
                    <span class="request-meta">👤 ${r.consumer_name} &middot; ${r.quantity} μερίδα</span>
                    <span class="request-status approved-text">✅ Εγκρίθηκε — αναμένεται παραλαβή</span>
                </div>
            </div>`;

        if (r.status === 'picked_up') {
            const earned = r.rating > 3
                ? (parseInt(r.credit_costs) + 1) * parseInt(r.quantity)
                : parseInt(r.credit_costs) * parseInt(r.quantity);
            return `
            <div class="request-card completed">
                <div class="request-info">
                    <h4>${r.title}</h4>
                    <span class="request-meta">👤 ${r.consumer_name} &middot; ${r.quantity} μερίδα</span>
                    <span class="request-status completed-text">✅ Παραλήφθηκε ${r.rating ? '&middot; ' + '⭐'.repeat(r.rating) : ''}</span>
                </div>
                <span class="credits-earned">+${earned} 🪙</span>
            </div>`;
        }

        if (r.status === 'rejected') return `
            <div class="request-card rejected">
                <div class="request-info">
                    <h4>${r.title}</h4>
                    <span class="request-meta">👤 ${r.consumer_name}</span>
                    <span class="request-status rejected-text">❌ Απορρίφθηκε</span>
                </div>
            </div>`;

        return '';
    }).join('');
}

function renderConsumerRequests(requests) {
    const container = document.querySelector('.requests-list');
    if (!container) return;

    if (!requests || requests.length === 0) {
        container.innerHTML = `<div class="empty-state"><span class="empty-icon">📋</span><p>Δεν έχεις παραγγελίες ακόμα</p></div>`;
        return;
    }

    container.innerHTML = requests.map(r => {
        if (r.status === 'pending') return `
            <div class="request-card pending">
                <div class="request-info">
                    <h4>${r.title}</h4>
                    <span class="request-meta">🍳 ${r.cook_name}</span>
                    <span class="request-status pending-text">⏳ Αναμένει έγκριση από τον μάγειρα</span>
                </div>
                <span class="request-cost">-${parseInt(r.credit_costs) * parseInt(r.quantity)} 🪙</span>
            </div>`;

        if (r.status === 'approved') return `
            <div class="request-card approved">
                <div class="request-info">
                    <h4>${r.title}</h4>
                    <span class="request-meta">🍳 ${r.cook_name} &middot; 📍 ${r.pickup_location}</span>
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
                    <h4>${r.title}</h4>
                    <span class="request-meta">🍳 ${r.cook_name}</span>
                    <span class="request-status completed-text">✅ Ολοκληρώθηκε</span>
                </div>
                <div class="rating-display">${r.rating ? '⭐'.repeat(r.rating) : ''}</div>
            </div>`;

        if (r.status === 'rejected') return `
            <div class="request-card rejected">
                <div class="request-info">
                    <h4>${r.title}</h4>
                    <span class="request-meta">🍳 ${r.cook_name}</span>
                    <span class="request-status rejected-text">❌ Απορρίφθηκε — τα credits επιστράφηκαν</span>
                </div>
            </div>`;

        if (r.status === 'no_show') return `
            <div class="request-card rejected">
                <div class="request-info">
                    <h4>${r.title}</h4>
                    <span class="request-status rejected-text">👻 No-show — ποινή -1 🪙</span>
                </div>
            </div>`;

        return '';
    }).join('');
}

function renderLeaderboard(leaderboard) {
    const container = document.querySelector('.leaderboard');
    if (!container || !leaderboard || leaderboard.length === 0) return;

    const topDonor = leaderboard[0];
    const others = leaderboard.slice(1);

    container.innerHTML = `
        <div class="top-donor">
            <div class="trophy">🥇</div>
            <div class="donor-info">
                <h3>${topDonor.username}</h3>
                <p class="donor-stats">${topDonor.total_given} μερίδες μοιράστηκαν</p>
            </div>
        </div>
        <h4>Υπόλοιποι Μάγειρες</h4>
        <ol class="donor-list">
            ${others.map(donor => `
                <li>
                    <span class="donor-name">${donor.username}</span>
                    <span class="donor-count">${donor.total_given} μερίδες</span>
                </li>
            `).join('')}
        </ol>
    `;
}

// --- Action Functions ---
async function ensureSession() {
    try {
        const res = await fetch(`${API_BASE}/auth.php?action=me`);
        if (res.status === 401 && currentUser) {
            await fetch(`${API_BASE}/auth.php?action=login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: currentUser.email })
            });
        }
    } catch (_) {}
}

function setupOrderModal() {
    const modal   = document.getElementById('order-modal');
    const closeBtn = document.getElementById('order-modal-close');
    const cancelBtn = document.getElementById('order-cancel-btn');
    const confirmBtn = document.getElementById('order-confirm-btn');
    const minusBtn = document.getElementById('qty-minus');
    const plusBtn  = document.getElementById('qty-plus');
    const qtyEl   = document.getElementById('qty-value');
    const costEl  = document.getElementById('order-total-cost');

    const close = () => modal.classList.remove('active');
    closeBtn.addEventListener('click', close);
    cancelBtn.addEventListener('click', close);
    modal.addEventListener('click', e => { if (e.target === modal) close(); });

    minusBtn.addEventListener('click', () => {
        const ad = adsCache[modal.dataset.adId];
        if (!ad) return;
        const qty = Math.max(1, parseInt(qtyEl.textContent) - 1);
        qtyEl.textContent = qty;
        costEl.textContent = `${ad.credit_costs * qty} 🪙`;
        minusBtn.disabled = qty <= 1;
        plusBtn.disabled  = qty >= ad.available_portions;
    });

    plusBtn.addEventListener('click', () => {
        const ad = adsCache[modal.dataset.adId];
        if (!ad) return;
        const qty = Math.min(ad.available_portions, parseInt(qtyEl.textContent) + 1);
        qtyEl.textContent = qty;
        costEl.textContent = `${ad.credit_costs * qty} 🪙`;
        minusBtn.disabled = qty <= 1;
        plusBtn.disabled  = qty >= ad.available_portions;
    });

    confirmBtn.addEventListener('click', async () => {
        const ad  = adsCache[modal.dataset.adId];
        const qty = parseInt(qtyEl.textContent);
        if (!ad) return;

        confirmBtn.disabled = true;
        confirmBtn.textContent = '⏳ ...';

        await ensureSession();

        try {
            const response = await fetch(`${API_BASE}/requests.php?action=create`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ad_id: ad.id, quantity: qty })
            });
            const data = await response.json();

            if (response.ok) {
                close();
                currentUser.credits -= data.transaction.total_cost;
                document.getElementById('user-credits').textContent = currentUser.credits;
                localStorage.setItem('unibite_user', JSON.stringify(currentUser));
                showOrderSuccess(ad.title, qty, data.transaction.total_cost);
                loadAds();
                loadRequests();
            } else {
                showOrderError(data.error || 'Σφάλμα παραγγελίας');
            }
        } catch (_) {
            showOrderError('Σφάλμα σύνδεσης');
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.textContent = '✅ Παραγγελία';
        }
    });
}

function orderFood(adId) {
    if (!currentUser) {
        alert('Πρέπει να συνδεθείς!');
        return;
    }

    const ad = adsCache[adId];
    if (!ad) return;

    const modal    = document.getElementById('order-modal');
    const qtyEl    = document.getElementById('qty-value');
    const costEl   = document.getElementById('order-total-cost');
    const minusBtn = document.getElementById('qty-minus');
    const plusBtn  = document.getElementById('qty-plus');

    modal.dataset.adId = adId;
    document.getElementById('order-modal-emoji').textContent = getFoodEmoji(ad.title);
    document.getElementById('order-modal-title').textContent = ad.title;
    document.getElementById('order-modal-desc').textContent  = ad.description || '';
    document.getElementById('qty-max-hint').textContent =
        `Διαθέσιμες μερίδες: ${ad.available_portions}`;

    qtyEl.textContent  = '1';
    costEl.textContent = `${ad.credit_costs} 🪙`;
    minusBtn.disabled  = true;
    plusBtn.disabled   = ad.available_portions <= 1;

    modal.classList.add('active');
}

function showOrderSuccess(title, qty, cost, customMsg) {
    const msg = document.createElement('div');
    msg.className = 'order-toast success';
    msg.textContent = customMsg || `✅ Παραγγελία για "${title}" (${qty} μερίδα) — -${cost} 🪙`;
    document.body.appendChild(msg);
    setTimeout(() => msg.classList.add('show'), 10);
    setTimeout(() => { msg.classList.remove('show'); setTimeout(() => msg.remove(), 400); }, 3500);
}

function showOrderError(text) {
    const msg = document.createElement('div');
    msg.className = 'order-toast error';
    msg.textContent = `❌ ${text}`;
    document.body.appendChild(msg);
    setTimeout(() => msg.classList.add('show'), 10);
    setTimeout(() => { msg.classList.remove('show'); setTimeout(() => msg.remove(), 400); }, 3500);
}

async function approveRequest(requestId) {
    await ensureSession();
    try {
        const response = await fetch(`${API_BASE}/requests.php?action=approve`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId })
        });
        const data = await response.json();
        if (response.ok) {
            showOrderSuccess('', 0, 0, '✅ Η παραγγελία εγκρίθηκε!');
            loadRequests();
        } else {
            showOrderError(data.error || 'Σφάλμα έγκρισης');
        }
    } catch (_) { showOrderError('Σφάλμα σύνδεσης'); }
}

async function rejectRequest(requestId) {
    await ensureSession();
    try {
        const response = await fetch(`${API_BASE}/requests.php?action=reject`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId })
        });
        const data = await response.json();
        if (response.ok) {
            showOrderSuccess('', 0, 0, '❌ Η παραγγελία απορρίφθηκε');
            loadRequests();
        } else {
            showOrderError(data.error || 'Σφάλμα απόρριψης');
        }
    } catch (_) { showOrderError('Σφάλμα σύνδεσης'); }
}

async function rateRequest(requestId, rating) {
    await ensureSession();
    try {
        const response = await fetch(`${API_BASE}/requests.php?action=rate`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId, rating })
        });
        const data = await response.json();
        if (response.ok) {
            showOrderSuccess('', 0, 0, `⭐ Βαθμολόγησες με ${rating} αστέρια! Ο μάγειρας πήρε +${data.reward} 🪙`);
            loadRequests();
        } else {
            showOrderError(data.error || 'Σφάλμα βαθμολογίας');
        }
    } catch (_) { showOrderError('Σφάλμα σύνδεσης'); }
}

// --- Helper Functions ---
function getFoodEmoji(title) {
    if (!title) return '🍽️';
    const t = title.toLowerCase();
    if (t.includes('παστίτσιο') || t.includes('μακαρον')) return '🍝';
    if (t.includes('σαλάτα')) return '🥗';
    if (t.includes('μουσακάς') || t.includes('παπά')) return '🥘';
    if (t.includes('πίτσα')) return '🍕';
    if (t.includes('σουβλάκι')) return '🥙';
    if (t.includes('γλυκό')) return '🍰';
    if (t.includes('κοτόπουλο')) return '🍗';
    if (t.includes('ψάρι')) return '🐟';
    return '🍽️';
}

function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString('el-GR', { hour: '2-digit', minute: '2-digit' });
}

// --- Global exports ---
window.orderFood = orderFood;
window.approveRequest = approveRequest;
window.rejectRequest = rejectRequest;
window.rateRequest = rateRequest;