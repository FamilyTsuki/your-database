-- ===============================================
-- Script d'initialisation de Mon Inventaire
-- ===============================================
-- Exécutez ce script dans phpMyAdmin ou en ligne de commande:
-- mysql -u root -p maison_db < setup_databases.sql

-- Table des bases de données
CREATE TABLE IF NOT EXISTS databases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_owner_db (name, owner_id)
);

-- Table des permissions sur les bases
CREATE TABLE IF NOT EXISTS database_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    database_id INT NOT NULL,
    user_id INT NOT NULL,
    permission VARCHAR(20) DEFAULT 'view', -- admin, edit, view
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (database_id) REFERENCES databases(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_permission (database_id, user_id)
);

-- Ajouter la colonne database_id à la table objets si elle n'existe pas
ALTER TABLE objets ADD COLUMN IF NOT EXISTS database_id INT DEFAULT 1;

-- Ajouter la clé étrangère
ALTER TABLE objets ADD FOREIGN KEY IF NOT EXISTS (database_id) REFERENCES databases(id) ON DELETE CASCADE;

-- Créer une base de données par défaut pour les anciennes données
INSERT INTO databases (id, name, description, owner_id) 
SELECT 1, 'Ma première base', 'Base par défaut', id 
FROM users 
WHERE id = 1 
LIMIT 1 
ON DUPLICATE KEY UPDATE name=name;

-- ✅ Initialisation terminée!

