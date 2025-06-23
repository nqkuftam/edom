# Обновяване на базата данни - Нова структура за обитатели

## Промени в структурата

Системата е обновена да използва ново поле `status` вместо старите полета `is_owner` и `is_primary` в таблицата `residents`.

### Нова структура на статусите:

- **owner** - Собственик на имота
- **tenant** - Наемател (основен обитател)
- **resident** - Обитател (постоянен жител)
- **user** - Ползвател (член на семейството, гост и т.н.)

## Инструкции за обновяване

### 1. Изпълнете SQL скрипта за обновяване

```sql
-- Обновяване на таблицата residents с ново status поле
USE edomoupravitel;

-- Добавяне на ново status поле
ALTER TABLE residents ADD COLUMN status ENUM('owner', 'tenant', 'resident', 'user') NOT NULL DEFAULT 'user' AFTER email;

-- Обновяване на съществуващите записи
-- Ако is_owner = 1, статусът става 'owner'
UPDATE residents SET status = 'owner' WHERE is_owner = 1;

-- Ако is_primary = 1 и is_owner = 0, статусът става 'tenant'
UPDATE residents SET status = 'tenant' WHERE is_primary = 1 AND is_owner = 0;

-- Премахване на старите полета
ALTER TABLE residents DROP COLUMN is_owner;
ALTER TABLE residents DROP COLUMN is_primary;
```

### 2. Или използвайте готовия файл

Изпълнете файла `update_residents_status.sql` в MySQL:

```bash
mysql -u username -p database_name < update_residents_status.sql
```

## Предимства на новата структура

1. **По-гъвкаво управление** - Можете да имате само един собственик, но множество наематели, обитатели и ползватели
2. **По-ясна логика** - Статусът е ясно дефиниран и не позволява конфликтни комбинации
3. **По-лесно разширяване** - Лесно може да се добавят нови статуси в бъдеще
4. **По-добра производителност** - По-малко полета за проверка

## Файлове, които са обновени

- `residents.php` - Основна страница за управление на обитатели
- `property.php` - Детайли за имот с обитатели
- `properties.php` - Списък с имоти
- `apartments.php` - Управление на апартаменти
- `get_residents.php` - API за вземане на обитатели
- `get_apartment.php` - API за вземане на данни за имот
- `add_residents_table.sql` - SQL за създаване на таблицата
- `update_residents_status.sql` - SQL за обновяване на съществуваща таблица

## Тестване

След обновяването проверете:

1. Добавяне на нов обитател с различни статуси
2. Редактиране на съществуващ обитател
3. Показване на обитателите в списъците
4. Филтриране по статус

## Връщане към старата структура (ако е необходимо)

Ако трябва да се върнете към старата структура, изпълнете:

```sql
-- Връщане към старата структура
ALTER TABLE residents ADD COLUMN is_owner BOOLEAN DEFAULT FALSE AFTER email;
ALTER TABLE residents ADD COLUMN is_primary BOOLEAN DEFAULT FALSE AFTER is_owner;

-- Обновяване на данните
UPDATE residents SET is_owner = 1 WHERE status = 'owner';
UPDATE residents SET is_primary = 1 WHERE status = 'tenant';

-- Премахване на новото поле
ALTER TABLE residents DROP COLUMN status;
```

**Забележка:** Това ще изисква и връщане на PHP файловете към старата версия. 