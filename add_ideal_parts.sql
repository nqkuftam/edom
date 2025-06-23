-- Добавяне на идеални части към съществуващата база данни
-- Изпълни този файл върху съществуващата база данни

USE edomoupravitel;

-- 1. Добавяне на колона ideal_parts в таблицата properties
ALTER TABLE properties ADD COLUMN ideal_parts DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Идеални части от сградата в проценти';

-- 2. Промяна на distribution_method в таблицата fees да включва by_ideal_parts
ALTER TABLE fees MODIFY COLUMN distribution_method ENUM('equal','by_people','by_area','by_ideal_parts') DEFAULT 'equal';

-- 3. Добавяне на индекс за по-бързо търсене по идеални части
ALTER TABLE properties ADD INDEX idx_ideal_parts (ideal_parts);

-- 4. Проверка на промените
SELECT 'Базата данни е успешно обновена с идеални части!' as status; 