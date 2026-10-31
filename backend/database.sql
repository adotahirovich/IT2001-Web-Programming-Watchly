CREATE DATABASE IF NOT EXISTS watchly
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE watchly;

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(30) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_id (role_id),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_products_category (category)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS carts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carts_user_product (user_id, product_id),
    KEY idx_carts_product_id (product_id),
    CONSTRAINT fk_carts_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_carts_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'paid', 'shipped', 'cancelled') NOT NULL DEFAULT 'pending',
    items_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user_id (user_id),
    KEY idx_orders_status (status),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_user_product (user_id, product_id),
    KEY idx_reviews_product_id (product_id),
    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_reviews_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (id, name) VALUES
    (1, 'admin'),
    (2, 'customer');

INSERT IGNORE INTO users (id, name, email, password_hash, role_id) VALUES
    (1, 'Watchly Administrator', 'admin@watchly.test', '$2y$10$TR6OY65ZL2/JdjwurRY.gudY25ee.mWUFa0rIOd79XOKC/22IzNwm', 1),
    (2, 'Demo Customer', 'customer@watchly.test', '$2y$10$TR6OY65ZL2/JdjwurRY.gudY25ee.mWUFa0rIOd79XOKC/22IzNwm', 2);

INSERT IGNORE INTO products (id, name, category, price, stock) VALUES
    (1, 'Chronograph Classic V8', 'Satovi', 450.00, 12),
    (2, 'Gold Luxury Diver', 'Satovi', 890.00, 3),
    (3, 'Diamond Silver Necklace', 'Nakit', 120.00, 25);

INSERT IGNORE INTO carts (id, user_id, product_id, quantity) VALUES
    (1, 2, 1, 1);

INSERT IGNORE INTO orders (id, user_id, total, status, items_json) VALUES
    (1, 2, 450.00, 'paid', '[{"product_id":1,"name":"Chronograph Classic V8","quantity":1,"unit_price":450.00}]');

INSERT IGNORE INTO reviews (id, user_id, product_id, rating, comment) VALUES
    (1, 2, 1, 5, 'Excellent quality and finish.');