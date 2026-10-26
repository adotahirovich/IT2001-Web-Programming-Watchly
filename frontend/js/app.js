/**
 * frontend demo.
 */

const viewCache = new Map();
const USERS_KEY = 'watchly_demo_users';
const SESSION_KEY = 'watchly_demo_session';
let currentRole = 'guest';
let currentUser = null;

const validPages = ['home', 'products', 'external-api', 'login', 'register', 'orders', 'admin'];

function readUsers() {
    try {
        const users = JSON.parse(localStorage.getItem(USERS_KEY) || '[]');
        return Array.isArray(users) ? users : [];
    } catch (_) {
        return [];
    }
}

function readSession() {
    try {
        return JSON.parse(localStorage.getItem(SESSION_KEY) || 'null');
    } catch (_) {
        return null;
    }
}

function isAdminUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.has('admin') || params.get('role') === 'admin' || window.location.hash === '#admin';
}

document.addEventListener('DOMContentLoaded', () => {
    // The dedicated admin URL takes priority. Example: index.html?admin=1
    if (isAdminUrl()) {
        currentRole = 'admin';
        currentUser = { name: 'Administrator', email: 'admin@watchly.ba' };
        renderRoleNavigation();
        showPage('admin');
        return;
    }

    const session = readSession();
    if (session && session.email) {
        currentUser = session;
        currentRole = 'customer';
    }
    renderRoleNavigation();

    const requestedPage = new URLSearchParams(window.location.search).get('page');
    showPage(validPages.includes(requestedPage) ? requestedPage : 'home');
});

async function showPage(pageId) {
    const appView = document.getElementById('app-view');
    if (!appView) return;
    if (!validPages.includes(pageId)) pageId = 'home';

    // Pages requiring a session cannot be opened as a guest.
    if (pageId === 'orders' && currentRole === 'guest') pageId = 'login';
    if (pageId === 'admin' && currentRole !== 'admin') {
        appView.innerHTML = '<div class="alert alert-warning">Admin panel je dostupan samo preko admin URL-a (npr. <code>index.html?admin=1</code>).</div>';
        return;
    }
    if ((pageId === 'login' || pageId === 'register') && currentRole !== 'guest') {
        pageId = currentRole === 'admin' ? 'admin' : 'products';
    }

    try {
        if (!viewCache.has(pageId)) {
            const response = await fetch(`views/${pageId}.html`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            viewCache.set(pageId, await response.text());
        }

        appView.innerHTML = viewCache.get(pageId).replace(
            /(<section\b[^>]*\bclass=["'][^"']*)\bd-none\s*/i,
            '$1'
        );

        document.querySelectorAll('.nav-item-link').forEach(link => link.classList.remove('active'));
        const activeNav = document.getElementById(`nav-${pageId}`);
        if (activeNav) activeNav.classList.add('active');

        renderRoleNavigation();
        window.scrollTo(0, 0);
    } catch (error) {
        console.error(`Could not load view "${pageId}":`, error);
        appView.innerHTML = `
            <div class="alert alert-danger" role="alert">
                Stranicu nije moguće učitati. Pokreni frontend preko XAMPP-a/Apachea ili VS Code Live Servera,
                a ne direktnim otvaranjem index.html preko file://.
            </div>`;
    }
}

function renderRoleNavigation() {
    document.querySelectorAll('.guest-only').forEach(el => {
        el.classList.toggle('d-none', currentRole !== 'guest');
    });
    document.querySelectorAll('.user-only').forEach(el => {
        el.classList.toggle('d-none', currentRole === 'guest');
    });
    document.querySelectorAll('.admin-only').forEach(el => {
        el.classList.toggle('d-none', currentRole !== 'admin');
    });

    const roleLabel = document.getElementById('current-role-label');
    if (roleLabel) {
        roleLabel.textContent = currentRole === 'admin' ? 'Administrator' : currentRole === 'customer' ? 'Korisnik' : 'Gost';
    }
}

function switchRole(role) {
    if (role === 'admin') {
        window.location.href = `${window.location.pathname}?admin=1`;
        return;
    }
    if (role === 'guest') logout();
    else showPage('products');
}

function showFormMessage(formId, message, type = 'danger') {
    const form = document.getElementById(formId);
    if (!form) return;
    let box = form.querySelector('.form-feedback');
    if (!box) {
        box = document.createElement('div');
        box.className = 'form-feedback alert mt-3 mb-0';
        box.setAttribute('role', 'alert');
        form.appendChild(box);
    }
    box.className = `form-feedback alert alert-${type} mt-3 mb-0`;
    box.textContent = message;
}

function handleLogin(event) {
    event.preventDefault();
    const email = (document.getElementById('login-email')?.value || '').trim().toLowerCase();
    const password = document.getElementById('login-password')?.value || '';
    if (!email || !password) return showFormMessage('login-form', 'Unesite email i lozinku.');

    const registeredUser = readUsers().find(user => user.email === email);
    if (registeredUser && registeredUser.password !== password) {
        return showFormMessage('login-form', 'Lozinka nije ispravna.');
    }

    // Milestone 1 demo: unregistered email can also be used for a demonstration login.
    currentUser = registeredUser
        ? { name: registeredUser.name, email: registeredUser.email }
        : { name: email.split('@')[0], email };
    currentRole = 'customer'; // Admin access is granted only by the dedicated admin URL.
    localStorage.setItem(SESSION_KEY, JSON.stringify(currentUser));
    renderRoleNavigation();
    alert('Prijava uspješna. Prijavljeni ste kao korisnik.');
    showPage('products');
}

function handleRegister(event) {
    event.preventDefault();
    const name = (document.getElementById('reg-name')?.value || '').trim();
    const email = (document.getElementById('reg-email')?.value || '').trim().toLowerCase();
    const password = document.getElementById('reg-password')?.value || '';
    if (!name || !email || password.length < 6) {
        return showFormMessage('register-form', 'Unesite ime, ispravan email i lozinku od najmanje 6 znakova.');
    }

    const users = readUsers();
    if (users.some(user => user.email === email)) {
        return showFormMessage('register-form', 'Račun s ovom email adresom već postoji. Prijavite se.');
    }

    users.push({ name, email, password });
    localStorage.setItem(USERS_KEY, JSON.stringify(users));
    currentUser = { name, email };
    currentRole = 'customer';
    localStorage.setItem(SESSION_KEY, JSON.stringify(currentUser));
    renderRoleNavigation();
    alert('Registracija uspješna. Automatski ste prijavljeni.');
    showPage('products');
}

function logout() {
    localStorage.removeItem(SESSION_KEY);
    currentUser = null;
    currentRole = 'guest';
    renderRoleNavigation();
    // Remove admin URL state too, so logout really returns to guest mode.
    window.history.replaceState({}, '', window.location.pathname);
    showPage('home');
}

function filterCategory(category) {
    document.querySelectorAll('.product-item').forEach(item => {
        const matches = category === 'all' || item.dataset.category === category;
        item.classList.toggle('d-none', !matches);
    });
    document.querySelectorAll('[onclick^="filterCategory"]').forEach(button => {
        button.classList.toggle('active', button.getAttribute('onclick') === `filterCategory('${category}')`);
    });
}

function addToCart(productName, price) {
    if (currentRole === 'guest') {
        alert('Za kupovinu se prvo prijavite ili registrujte.');
        showPage('login');
        return;
    }
    alert(`Demo kupovina: "${productName}" (${Number(price).toFixed(2)} EUR) dodan je u korpu.`);
}

function convertPrices(currency) {
    const statusBox = document.getElementById('currency-status');
    if (!statusBox) return;
    if (currency === 'EUR') {
        statusBox.innerHTML = '<i class="fa-solid fa-info-circle me-1"></i> Osnovna valuta: EUR. Stvarni kurs će se učitavati preko backend-a i Frankfurter API-ja u narednom milestone-u.';
        return;
    }
    statusBox.innerHTML = `<i class="fa-solid fa-info-circle me-1"></i> Odabrana valuta: <strong>${currency}</strong>. Ovo je samo UI demonstracija; stvarni kurs još nije povezan.`;
}

function openAddProductModal() {
    alert('Admin demo: dodavanje proizvoda bit će povezano s backend CRUD funkcionalnošću u narednim milestone-ima.');
}
