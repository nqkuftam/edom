-- Добавяне на колона type в таблицата apartments
ALTER TABLE apartments ADD COLUMN type VARCHAR(50) DEFAULT 'apartment' AFTER number;

-- Добавяне на индекса за type
CREATE INDEX idx_apartment_type ON apartments(type); 