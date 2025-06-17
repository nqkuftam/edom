-- Създаване на потребител за базата данни
CREATE USER 'edomoupravitel_user'@'%' IDENTIFIED BY 'E$L2NEN9uk';

-- Даване на права на потребителя за базата данни
GRANT ALL PRIVILEGES ON edomoupravitel.* TO 'edomoupravitel_user'@'%';

-- Прилагане на промените
FLUSH PRIVILEGES; 