-- Добавяне на нови колони в таблицата residents
ALTER TABLE residents
ADD COLUMN egn VARCHAR(10) AFTER last_name,
ADD COLUMN ownership_documents TEXT AFTER egn,
ADD COLUMN owner_type ENUM('individual', 'company', 'inheritance', 'other') DEFAULT 'individual' AFTER is_owner;

-- Създаване на таблица за история на обитателите
CREATE TABLE IF NOT EXISTS resident_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    apartment_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    egn VARCHAR(10),
    phone VARCHAR(20),
    email VARCHAR(100),
    is_owner BOOLEAN DEFAULT FALSE,
    owner_type ENUM('individual', 'company', 'inheritance', 'other') DEFAULT 'individual',
    is_primary BOOLEAN DEFAULT FALSE,
    move_in_date DATE NOT NULL,
    move_out_date DATE,
    ownership_documents TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE
); 