-- Създаване на базата данни
CREATE DATABASE IF NOT EXISTS edomoupravitel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edomoupravitel;

-- Таблица за потребители
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица за сгради
CREATE TABLE IF NOT EXISTS buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    floors INT NOT NULL,
    total_apartments INT NOT NULL,
    generate_day INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица за апартаменти
CREATE TABLE IF NOT EXISTS apartments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    number VARCHAR(20) NOT NULL,
    floor INT NOT NULL,
    owner_name VARCHAR(100),
    owner_phone VARCHAR(20),
    owner_email VARCHAR(100),
    area DECIMAL(10,2) NOT NULL,
    people_count INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_apartment (building_id, number)
);

-- Таблица за такси (обща информация)
CREATE TABLE IF NOT EXISTS fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('monthly','temporary') DEFAULT 'monthly',
    total_amount FLOAT NOT NULL,
    description TEXT,
    distribution_method ENUM('equal','by_people','by_area') DEFAULT 'equal',
    months_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица за разпределение по апартаменти
CREATE TABLE IF NOT EXISTS fee_apartments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_id INT NOT NULL,
    apartment_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE
);

-- Таблица за плащания
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apartment_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE
);

-- Таблица за бележки към сграда
CREATE TABLE IF NOT EXISTS building_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    user_id INT,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Таблица за каси
CREATE TABLE IF NOT EXISTS cashboxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
);

-- Таблица за транзакции по каса
CREATE TABLE IF NOT EXISTS cashbox_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cashbox_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type ENUM('in','out') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cashbox_id) REFERENCES cashboxes(id) ON DELETE CASCADE
);

-- Създаване на администраторски акаунт (парола: admin123)
INSERT INTO users (username, password, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin'); 