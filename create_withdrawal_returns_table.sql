-- Създаване на таблица за връщания на теглени пари
CREATE TABLE `withdrawal_returns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `withdrawal_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `withdrawal_id` (`withdrawal_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_withdrawal_returns_withdrawal` FOREIGN KEY (`withdrawal_id`) REFERENCES `withdrawals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci; 