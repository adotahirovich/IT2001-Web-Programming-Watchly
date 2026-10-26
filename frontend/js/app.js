const viewCache = new Map();
const USERS_KEY = 'watchly_demo_users';
const SESSION_KEY = 'watchly_demo_session';
const CART_KEY = 'watchly_demo_cart';
const ORDERS_KEY = 'watchly_demo_orders';
let currentRole = 'guest';
let currentUser = null;

const validPages = ['home', 'products', 'cart', 'external-api', 'login', 'register', 'orders', 'admin'];

function normalizeEmail(email) {
    return typeof email === 'string' ? email.trim().toLowerCase() : '';
}

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
    updateCartCount();

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

    if (pageId === 'orders' && currentRole === 'guest') pageId = 'login';
    if (pageId === 'admin' && currentRole !== 'admin') {
        appView.innerHTML = '<div class="alert alert-warning">The admin panel is available through the admin URL (for example, <code>index.html?admin=1</code>).</div>';
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
        if (pageId === 'cart') renderCart();
        if (pageId === 'orders') renderOrders();
        window.scrollTo(0, 0);
    } catch (error) {
        console.error(`Could not load view "${pageId}":`, error);
        appView.innerHTML = `
            <div class="alert alert-danger" role="alert">
                This page could not be loaded. Run the frontend through XAMPP/Apache or VS Code Live Server,
                rather than opening index.html directly with file://.
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
    document.querySelectorAll('.logout-only').forEach(el => {
        el.classList.toggle('d-none', currentRole === 'guest');
    });

    const roleLabel = document.getElementById('current-role-label');
    if (roleLabel) {
        roleLabel.textContent = currentRole === 'admin' ? 'Administrator' : currentRole === 'customer' ? 'Customer' : 'Guest';
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
    if (!email || !password) return showFormMessage('login-form', 'Enter your email and password.');

    const registeredUser = readUsers().find(user => user.email === email);
    if (registeredUser && registeredUser.password !== password) {
        return showFormMessage('login-form', 'The password is incorrect.');
    }

    currentUser = registeredUser
        ? { name: registeredUser.name, email: registeredUser.email }
        : { name: email.split('@')[0], email };
    currentRole = 'customer';
    localStorage.setItem(SESSION_KEY, JSON.stringify(currentUser));
    renderRoleNavigation();
    alert('Login successful. You are signed in as a customer.');
    showPage(readCart().length ? 'cart' : 'products');
}

function handleRegister(event) {
    event.preventDefault();
    const name = (document.getElementById('reg-name')?.value || '').trim();
    const email = (document.getElementById('reg-email')?.value || '').trim().toLowerCase();
    const password = document.getElementById('reg-password')?.value || '';
    if (!name || !email || password.length < 6) {
        return showFormMessage('register-form', 'Enter your name, a valid email, and a password of at least 6 characters.');
    }

    const users = readUsers();
    if (users.some(user => user.email === email)) {
        return showFormMessage('register-form', 'An account with this email already exists. Please log in.');
    }

    users.push({ name, email, password });
    localStorage.setItem(USERS_KEY, JSON.stringify(users));
    currentUser = { name, email };
    currentRole = 'customer';
    localStorage.setItem(SESSION_KEY, JSON.stringify(currentUser));
    renderRoleNavigation();
    alert('Registration successful. You are now signed in.');
    showPage(readCart().length ? 'cart' : 'products');
}

function logout() {
    localStorage.removeItem(SESSION_KEY);
    currentUser = null;
    currentRole = 'guest';
    renderRoleNavigation();
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
    const parsedPrice = Number(price);
    if (typeof productName !== 'string' || productName.trim() === '' || !Number.isFinite(parsedPrice) || parsedPrice < 0) {
        console.error('Invalid product data; item was not added to the cart.');
        return;
    }

    const cart = readCart();
    const item = cart.find(entry => entry.name === productName);
    if (item) {
        item.quantity += 1;
    } else {
        cart.push({ name: productName, price: parsedPrice, quantity: 1 });
    }
    if (saveCart(cart)) {
        alert(`"${productName}" was added to your cart.`);
    }
}

function readCart() {
    try {
        const cart = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
        if (!Array.isArray(cart)) return [];
        return cart.filter(item =>
            item &&
            typeof item.name === 'string' &&
            Number.isFinite(Number(item.price)) &&
            Number(item.price) >= 0 &&
            Number.isInteger(Number(item.quantity)) &&
            Number(item.quantity) > 0
        ).map(item => ({
            name: item.name === 'Srebrna Ogrlica Diamond' ? 'Diamond Silver Necklace' : item.name,
            price: Number(item.price),
            quantity: Number(item.quantity)
        }));
    } catch (_) {
        return [];
    }
}

function saveCart(cart) {
    try {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    } catch (error) {
        console.error('Could not save the cart:', error);
        alert('Your cart could not be saved in this browser.');
        return false;
    }
    updateCartCount();
    renderCart();
    return true;
}

function updateCartCount() {
    const badge = document.getElementById('cart-count');
    if (!badge) return;
    badge.textContent = String(readCart().reduce((total, item) => total + item.quantity, 0));
}

function renderCart() {
    const itemsContainer = document.getElementById('cart-items');
    if (!itemsContainer) return;

    const cart = readCart();
    const isEmpty = cart.length === 0;
    document.getElementById('cart-empty')?.classList.toggle('d-none', !isEmpty);
    document.getElementById('cart-content')?.classList.toggle('d-none', isEmpty);
    itemsContainer.innerHTML = cart.map((item, index) => `
        <tr>
            <td>${escapeHtml(item.name)}</td>
            <td>${item.price.toFixed(2)} EUR</td>
            <td>
                <div class="btn-group btn-group-sm" role="group" aria-label="Quantity">
                    <button class="btn btn-outline-secondary" onclick="changeCartQuantity(${index}, -1)" aria-label="Decrease quantity">−</button>
                    <span class="btn btn-outline-secondary disabled">${item.quantity}</span>
                    <button class="btn btn-outline-secondary" onclick="changeCartQuantity(${index}, 1)" aria-label="Increase quantity">+</button>
                </div>
            </td>
            <td>${(item.price * item.quantity).toFixed(2)} EUR</td>
            <td><button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})" aria-label="Remove ${escapeHtml(item.name)}"><i class="fa-solid fa-trash"></i></button></td>
        </tr>
    `).join('');
    document.getElementById('cart-total').textContent =
        `${cart.reduce((total, item) => total + item.price * item.quantity, 0).toFixed(2)} EUR`;
    updateCartCount();
}

function changeCartQuantity(index, change) {
    const cart = readCart();
    if (!cart[index]) return;
    cart[index].quantity += change;
    if (cart[index].quantity <= 0) cart.splice(index, 1);
    saveCart(cart);
}

function removeFromCart(index) {
    const cart = readCart();
    if (!cart[index]) return;
    cart.splice(index, 1);
    saveCart(cart);
}

function clearCart() {
    saveCart([]);
}

function checkoutCart() {
    const cart = readCart();
    if (cart.length === 0) return;
    if (currentRole === 'guest' || !currentUser?.email) {
        alert('Please log in before completing your purchase. Your cart will be saved.');
        showPage('login');
        return;
    }

    const orders = readOrders();
    const order = {
        id: `ORD-${Date.now()}`,
        date: new Date().toISOString(),
        status: 'Completed (demo)',
        email: normalizeEmail(currentUser.email),
        items: cart.map(item => ({ ...item })),
        total: cart.reduce((total, item) => total + item.price * item.quantity, 0)
    };

    try {
        localStorage.setItem(ORDERS_KEY, JSON.stringify([order, ...orders]));
        const savedOrders = JSON.parse(localStorage.getItem(ORDERS_KEY) || '[]');
        if (!Array.isArray(savedOrders) || !savedOrders.some(savedOrder => savedOrder.id === order.id)) {
            throw new Error('The order was not found after saving.');
        }
        localStorage.removeItem(CART_KEY);
    } catch (error) {
        console.error('Could not save the demo order:', error);
        alert('Checkout could not be completed. Check My Orders before trying again.');
        return;
    }

    updateCartCount();
    renderCart();
    alert('Demo order completed. You can view it in My Orders.');
    showPage('orders');
}

function readOrders() {
    try {
        const orders = JSON.parse(localStorage.getItem(ORDERS_KEY) || '[]');
        return Array.isArray(orders) ? orders.filter(order =>
            order &&
            typeof order.id === 'string' &&
            typeof order.email === 'string' &&
            typeof order.status === 'string' &&
            Number.isFinite(Date.parse(order.date)) &&
            Array.isArray(order.items) &&
            order.items.every(item =>
                item &&
                typeof item.name === 'string' &&
                Number.isFinite(Number(item.price)) &&
                Number.isInteger(Number(item.quantity)) &&
                Number(item.quantity) > 0
            ) &&
            Number.isFinite(Number(order.total))
        ) : [];
    } catch (_) {
        return [];
    }
}

function renderOrders() {
    const tableBody = document.getElementById('orders-list');
    const emptyMessage = document.getElementById('orders-empty');
    if (!tableBody || !emptyMessage) return;

    const userEmail = normalizeEmail(currentUser?.email);
    const orders = readOrders().filter(order => normalizeEmail(order.email) === userEmail);
    emptyMessage.classList.toggle('d-none', orders.length > 0);
    tableBody.closest('table').classList.toggle('d-none', orders.length === 0);
    tableBody.innerHTML = orders.map(order => `
        <tr>
            <td>${escapeHtml(order.id)}</td>
            <td>${new Date(order.date).toLocaleDateString('en-GB')}</td>
            <td><span class="badge bg-success">${escapeHtml(order.status)}</span></td>
            <td>${Number(order.total).toFixed(2)} EUR</td>
            <td>${order.items.map(item => `${escapeHtml(item.name)} × ${Number(item.quantity)}`).join('<br>')}</td>
        </tr>
    `).join('');
}

function escapeHtml(value) {
    return value.replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[character]);
}

function convertPrices(currency) {
    const statusBox = document.getElementById('currency-status');
    if (!statusBox) return;
    if (currency === 'EUR') {
        statusBox.innerHTML = '<i class="fa-solid fa-info-circle me-1"></i> Base currency: EUR. Live exchange rates will be connected in a later milestone.';
        return;
    }
    statusBox.innerHTML = `<i class="fa-solid fa-info-circle me-1"></i> Selected currency: <strong>${currency}</strong>. This is a UI demo; live rates are not connected yet.`;
}

function openAddProductModal() {
    alert('Admin demo: adding products will be connected to backend CRUD functionality in a later milestone.');
}
