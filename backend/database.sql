CREATE DATABASE IF NOT EXISTS watchly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE watchly;

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
);
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
);
CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category ENUM('Satovi','Nakit') NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  image_url VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('pending','paid','shipped','cancelled') NOT NULL DEFAULT 'pending',
  items_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment VARCHAR(1000) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id)
);

INSERT INTO roles(name,description) VALUES
('user','Standardni kupac'),('admin','Administrator prodavnice');

INSERT INTO products(name,category,description,price,stock,image_url) VALUES
('Classic Noir','Satovi','Minimalistički sat s tamnim brojčanikom i čeličnim detaljima.',189.00,12,NULL),
('Golden Hour','Satovi','Elegantni sat u zlatnom tonu za posebne prilike i svaki dan.',249.00,8,NULL),
('Silver Line','Nakit','Srebrna narukvica jednostavnog i bezvremenskog dizajna.',59.00,20,NULL),
('Luna Earrings','Nakit','Diskretne naušnice koje se lako uklapaju u svaki stil.',39.00,25,NULL),
('Heritage Automatic','Satovi','Klasičan automatski sat inspirisan tradicionalnim urarstvom.',399.00,5,NULL);

-- Test admin: admin@watchly.local / WatchlyAdmin2026!
-- Create the account through the register form, then promote it:
-- UPDATE users SET role_id = 2 WHERE email = 'admin@watchly.local';
