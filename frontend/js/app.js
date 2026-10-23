/**
 * Watchly - Single Page Application (SPA) Controller
 * Handles navigation without full page reloads and simulates user roles for Milestone 1.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Postavi početnu stranicu na 'home'
    showPage('home');
});

/**
 * Prikazuje traženu sekciju i sakriva ostale
 * @param {string} pageId - ID stranice (sekcije) bez prefiksa 'page-'
 */
function showPage(pageId) {
    // Sakrij sve sekcije sa klasom .page-section
    const sections = document.querySelectorAll('.page-section');
    sections.forEach(section => {
        section.classList.add('d-none');
    });

    // Prikaži traženi ID
    const targetSection = document.getElementById('page-' + pageId);
    if (targetSection) {
        targetSection.classList.remove('d-none');
    }

    // Ažuriraj aktivni link u navbaru
    const navLinks = document.querySelectorAll('.nav-item-link');
    navLinks.forEach(link => link.classList.remove('active'));

    const activeNav = document.getElementById('nav-' + pageId);
    if (activeNav) {
        activeNav.classList.add('active');
    }

    // Skroluj na vrh stranice
    window.scrollTo(0, 0);
}

/**
 * Simulacija promjene uloge korisnika za pregled Milestone 1
 * @param {string} role - 'guest', 'customer', ili 'admin'
 */
function switchRole(role) {
    const guestElements = document.querySelectorAll('.guest-only');
    const userElements = document.querySelectorAll('.user-only');
    const adminElements = document.querySelectorAll('.admin-only');

    if (role === 'guest') {
        guestElements.forEach(el => el.classList.remove('d-none'));
        userElements.forEach(el => el.classList.add('d-none'));
        adminElements.forEach(el => el.classList.add('d-none'));
        showPage('home');
    } else if (role === 'customer') {
        guestElements.forEach(el => el.classList.add('d-none'));
        userElements.forEach(el => el.classList.remove('d-none'));
        adminElements.forEach(el => el.classList.add('d-none'));
        showPage('products');
    } else if (role === 'admin') {
        guestElements.forEach(el => el.classList.add('d-none'));
        userElements.forEach(el => el.classList.remove('d-none'));
        adminElements.forEach(el => el.classList.remove('d-none'));
        showPage('admin');
    }
}

/**
 * Filtriranje po kategorijama na frontendu
 */
function filterCategory(category) {
    const items = document.querySelectorAll('.product-item');
    items.forEach(item => {
        if (category === 'all' || item.getAttribute('data-category') === category) {
            item.classList.remove('d-none');
        } else {
            item.classList.add('d-none');
        }
    });
}

/**
 * Simulacija prijava forme
 */
function handleLogin(event) {
    event.preventDefault();
    const email = document.getElementById('login-email').value;
    
    if (email.includes('admin')) {
        document.getElementById('role-simulator').value = 'admin';
        switchRole('admin');
        alert('Uspješna prijava kao Administrator!');
    } else {
        document.getElementById('role-simulator').value = 'customer';
        switchRole('customer');
        alert('Uspješna prijava kao Korisnik!');
    }
}

/**
 * Simulacija registracija forme
 */
function handleRegister(event) {
    event.preventDefault();
    alert('Registracija uspješna! Prijavljeni ste na sistem.');
    document.getElementById('role-simulator').value = 'customer';
    switchRole('customer');
}

/**
 * Odjava
 */
function logout() {
    document.getElementById('role-simulator').value = 'guest';
    switchRole('guest');
    alert('Odjavljeni ste sa sistema.');
}

/**
 * Simulacija kupovine
 */
function addToCart(productName, price) {
    alert(`Proizvod "${productName}" (${price} EUR) je dodan u korpu!`);
}

/**
 * Simulacija eksternog API pretvarača valuta
 */
function convertPrices(currency) {
    const statusBox = document.getElementById('currency-status');
    let rate = 1;
    let symbol = '€';

    if (currency === 'USD') { rate = 1.08; symbol = '$'; }
    if (currency === 'GBP') { rate = 0.85; symbol = '£'; }
    if (currency === 'CHF') { rate = 0.96; symbol = 'CHF'; }

    statusBox.innerHTML = `<i class="fa-solid fa-check-circle text-success me-1"></i> Prikaz u valuti <strong>${currency}</strong> (Kurs: 1 EUR = ${rate} ${currency}). Od M3 se poziva Frankfurter API.`;
}