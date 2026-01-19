#!/bin/bash

# Script d'initialisation de la base de données pour Mon Inventaire

echo "📦 Mon Inventaire - Initialisation"
echo "===================================="
echo ""

# Configuration
DB_HOST="localhost"
DB_USER="root"
DB_PASS=""
DB_NAME="maison_db"

echo "Connexion à MySQL..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << EOF

-- Créer la table users
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Créer la table databases
CREATE TABLE IF NOT EXISTS databases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_owner_db (name, owner_id)
);

-- Créer la table database_permissions
CREATE TABLE IF NOT EXISTS database_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    database_id INT NOT NULL,
    user_id INT NOT NULL,
    permission VARCHAR(20) DEFAULT 'view',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (database_id) REFERENCES databases(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_permission (database_id, user_id)
);

-- Créer/Modifier la table objets
CREATE TABLE IF NOT EXISTS objets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    categorie VARCHAR(50),
    quantite INT DEFAULT 0,
    image_path VARCHAR(255),
    database_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (database_id) REFERENCES databases(id) ON DELETE CASCADE
);

-- Ajouter la colonne database_id si elle n'existe pas
ALTER TABLE objets ADD COLUMN database_id INT DEFAULT 1;

-- Insérer la base par défaut
INSERT INTO databases (id, name, description, owner_id) VALUES (1, 'Ma première base', 'Base par défaut', 1) ON DUPLICATE KEY UPDATE name=name;

EOF

if [ $? -eq 0 ]; then
    echo "✅ Base de données initialisée avec succès!"
    echo ""
    echo "Vous pouvez maintenant accéder à l'application:"
    echo "📍 http://localhost/your-database/public/"
else
    echo "❌ Erreur lors de l'initialisation"
    exit 1
fi
