# 📦 Mon Inventaire - Guide d'Installation et Utilisation

## 🚀 Installation

### 1. Prérequis
- PHP 7.4+
- MySQL/MariaDB
- Apache/Nginx
- phpMyAdmin (recommandé)

### 2. Configuration de la base de données

Exécutez les commandes SQL suivantes dans phpMyAdmin ou en ligne de commande :

```sql
-- Créer la base de données
CREATE DATABASE IF NOT EXISTS maison_db;

-- Utiliser la base
USE maison_db;

-- Créer la table users
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Créer la table objets
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

-- Insérer une base de données par défaut
INSERT INTO databases (id, name, description, owner_id) VALUES (1, 'Ma première base', 'Base par défaut', 1) ON DUPLICATE KEY UPDATE name=name;
```

### 3. Déploiement

1. Déployez les fichiers dans votre dossier web (par ex: `/var/www/html/your-database`)
2. Modifiez la config au besoin dans `config/config.php`
3. Accédez à `http://localhost/your-database/public/setup.php`
4. Cliquez sur "Initialiser la base de données"

## 📱 Utilisation

### Première connexion

1. Allez à `http://localhost/your-database/public/register.php`
2. Créez un compte
3. Connectez-vous à `http://localhost/your-database/public/login.php`

### Interface du Dashboard

Une fois connecté, vous arrivez sur le dashboard qui affiche:
- **Vos bases de données**: Toutes les bases auxquelles vous avez accès
- **Badge propriétaire**: Indication si vous êtes propriétaire d'une base
- **Permission badge**: Votre niveau de permission (Lecture, Modif, Admin)

### Créer une base de données

1. Cliquez sur "Créer une nouvelle base"
2. Entrez un nom et une description (optionnelle)
3. La base est créée avec vous comme propriétaire

### Consulter une base

1. Cliquez sur "Consulter" sur une base
2. Vous voyez tous les objets ajoutés
3. Si vous avez la permission d'édition, vous pouvez:
   - Ajouter des objets
   - Modifier les quantités
   - Ajouter des images

### Gérer les permissions

**Uniquement propriétaire:**

1. Cliquez sur "Paramètres" sur votre base
2. Onglet "Partage d'accès":
   - **Ajouter un utilisateur**: Entrez son pseudo et le niveau d'accès
   - **Permissions disponibles**:
     - `Lecture seule`: Consulter uniquement
     - `Modifier`: Consulter + Ajouter/Modifier objets
     - `Admin`: Accès complet + Gestion des permissions

### Renommer les catégories

1. Allez dans les paramètres de votre base
2. Section "Renommer les catégories"
3. Modifiez le nom et confirmez

### Supprimer une base

1. Allez dans les paramètres de votre base
2. Section "Zone de danger"
3. Cochez la confirmation et cliquez "Supprimer"
4. **Attention**: Cette action est irréversible!

## 🔐 Sécurité

- ✅ Mots de passe hashés (Bcrypt)
- ✅ Protection CSRF sur tous les formulaires
- ✅ Validation des inputs
- ✅ Prepared statements (injection SQL)
- ✅ Gestion des sessions sécurisée
- ✅ Vérification des permissions sur chaque action

## 📂 Structure du projet

```
your-database/
├── public/              # Fichiers accessibles au web
│   ├── index.php       # Dashboard
│   ├── login.php       # Connexion
│   ├── register.php    # Inscription
│   ├── setup.php       # Initialisation
│   ├── database.php    # Consultation base
│   ├── database-settings.php  # Paramètres
│   ├── css/
│   └── js/
├── src/
│   ├── Models/         # Couche données
│   ├── Controllers/    # Logique métier
│   └── Helpers/        # Utilitaires
├── templates/          # Templates HTML
├── config/
│   └── config.php     # Configuration
└── setup_databases.sql # Script SQL
```

## 🐛 Dépannage

### Les tables n'existent pas
- Allez sur `http://localhost/your-database/public/setup.php`
- Cliquez sur "Initialiser la base de données"

### Erreur de connexion BD
- Vérifiez les identifiants dans `config/config.php`
- Assurez-vous que MySQL est lancé

### Oublié mot de passe
- Actuellement pas de fonction "Mot de passe oublié"
- Contactez un administrateur pour réinitialiser
