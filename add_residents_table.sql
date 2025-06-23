-- Таблица за обитатели
CREATE TABLE IF NOT EXISTS residents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    status ENUM('owner', 'tenant', 'resident', 'user') NOT NULL DEFAULT 'user',
    move_in_date DATE NOT NULL,
    move_out_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Коментар за обяснение на статусите:
-- owner: Собственик на имота
-- tenant: Наемател (основен обитател)
-- resident: Обитател (постоянен жител)
-- user: Ползвател (член на семейството, гост и т.н.) 