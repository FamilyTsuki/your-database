# 🔄 Refactorisation - Système Multi-Bases de Données

## 📝 Résumé des changements

Le système a été refactorisé pour supporter **plusieurs bases de données par utilisateur** avec **gestion des permissions** granulaires.

### 🎯 Architecture avant
- Une seule table `objets` globale
- Tous les objets visibles par tous les utilisateurs connectés
- Pas de gestion des permissions

### 🎯 Architecture après
- **Multiple bases de données** par utilisateur
- **Système de permissions** (admin, edit, view)
- **Partage d'accès** avec d'autres utilisateurs
- **Gestion complète** des paramètres

---

## 📂 Fichiers créés

### Models
- **`src/Models/DatabaseModel.php`** - Gestion des bases de données et permissions

### Controllers  
- **`src/Controllers/DatabaseController.php`** - Logique métier pour les bases

### Pages publiques
- **`public/dashboard.phtml`** - Template du dashboard
- **`public/database.php`** - Consulter et ajouter objets
- **`public/database-settings.php`** - Paramètres de la base
- **`public/setup.php`** - Initialisation de la BD
- **`public/test.php`** - Tests de configuration

### Documentation
- **`SETUP.md`** - Guide d'installation et utilisation
- **`setup_databases.sql`** - Script SQL d'initialisation
- **`init_db.sh`** - Script Bash d'initialisation (Linux/Mac)
- **`init_db.ps1`** - Script PowerShell d'initialisation (Windows)

---

## 🗄️ Nouvelles tables de base de données

### `databases`
```sql
id (INT, PK)
name (VARCHAR 100) - Nom de la base
description (TEXT) - Description optionnelle
owner_id (INT, FK) - Propriétaire
created_at (TIMESTAMP)
```

### `database_permissions`
```sql
id (INT, PK)
database_id (INT, FK)
user_id (INT, FK)
permission (VARCHAR 20) - admin | edit | view
created_at (TIMESTAMP)
```

### `objets` (modifiée)
```sql
... colonnes existantes ...
database_id (INT, FK) - Référence à la base (DEFAULT 1)
```

---

## 🔐 Système de permissions

### Niveaux d'accès
1. **view** - Lecture seule
   - Consulter les objets
   - Pas de modifications

2. **edit** - Modification
   - Consulter les objets
   - Ajouter/modifier/supprimer objets
   - Modifier quantités et images
   - Pas d'accès aux paramètres

3. **admin** - Administrateur complet
   - Tous les droits d'édition
   - Partager avec d'autres utilisateurs
   - Modifier paramètres
   - Renommer catégories
   - Supprimer la base

### Attribution des permissions
- Le **créateur** d'une base reçoit automatiquement le rôle `admin`
- Le propriétaire peut ajouter/retirer des utilisateurs
- Les permissions peuvent être modifiées à tout moment

---

## 🌐 Flux utilisateur

### Première connexion
1. L'utilisateur se connecte → Redirection vers `index.php`
2. Il est redirigé vers `setup.php` si les tables n'existent pas
3. Après initialisation → Accès au **Dashboard**

### Dashboard (index.php)
- ✓ Affiche **toutes les bases accessibles**
- ✓ Badge "Propriétaire" pour les bases propres
- ✓ Badge permission pour les bases partagées
- ✓ Bouton "Consulter" pour ouvrir une base
- ✓ Bouton "Paramètres" (propriétaire uniquement)
- ✓ Formulaire pour créer une nouvelle base

### Consulter une base (database.php?id=X)
- ✓ Affiche **tous les objets** de la base
- ✓ Ajouter objets (si permission edit/admin)
- ✓ Modifier quantités (si permission edit/admin)
- ✓ Lien vers les paramètres (propriétaire uniquement)

### Paramètres (database-settings.php?id=X)
**Uniquement accessible par le propriétaire**

Sections:
1. **Informations générales**
   - Renommer la base
   - Modifier la description

2. **Partage d'accès**
   - Ajouter utilisateurs
   - Assigner permissions
   - Voir et retirer utilisateurs existants

3. **Renommer les catégories**
   - Liste des catégories
   - Formulaires de renommage

4. **Zone de danger**
   - Supprimer la base (irréversible)

---

## 🎨 Changements CSS

Nouveaux styles pour:
- Dashboard grid (cards des bases)
- Formulaires de création
- Badges et permissions
- Page paramètres
- Tables d'utilisateurs
- Zone de danger (delete)

---

## 🔄 Changements fichiers existants

### `public/index.php`
- Avant: Affichait la page d'accueil simple
- Après: Dashboard avec liste des bases de données

### `config/config.php`
- Ajout de l'include pour `DatabaseModel.php`
- Ajout de l'include pour `DatabaseController.php`

### `public/css/style.css`
- +200 lignes de CSS pour les nouveaux composants

---

## 🚀 Initialisation requise

### Option 1: Web UI (Recommandée)
1. Accédez à `http://localhost/your-database/public/setup.php`
2. Cliquez "Initialiser la base de données"

### Option 2: Ligne de commande (Linux/Mac)
```bash
bash init_db.sh
```

### Option 3: PowerShell (Windows)
```powershell
powershell -ExecutionPolicy Bypass -File init_db.ps1
```

### Option 4: phpMyAdmin
Importez `setup_databases.sql` ou collez-le dans l'onglet SQL

---

## 📋 Vérification

Utilisez `public/test.php` pour vérifier:
- ✓ Connexion à la BD
- ✓ Existence des tables
- ✓ Classes chargées
- ✓ Session active
- ✓ Authentification

---

## 🔒 Sécurité

Maintenues/Améliorées:
- ✅ Protection CSRF sur tous les formulaires
- ✅ Validation des inputs
- ✅ Prepared statements (pas d'injection SQL)
- ✅ Vérification des permissions sur chaque action
- ✅ Sessions sécurisées
- ✅ Mots de passe hashés

---

## ⚠️ Notes importantes

1. **Données existantes**: Les objets existants seront associés à la base avec `database_id = 1`
2. **Retrocompatibilité**: Les anciennes données restent accessibles dans la base "Ma première base"
3. **Propriétaire**: Seul le propriétaire peut accéder aux paramètres
4. **Suppression**: Supprimer une base supprime tous les objets et permissions
5. **Permissions**: Les changements de permission sont immédiat

---

## 🐛 Dépannage

### Les tables n'existent pas
→ Allez sur `setup.php` pour initialiser

### Erreur lors de la création d'une base
→ Vérifiez que vous êtes authentifié

### Pas d'accès à une base partagée
→ Demandez au propriétaire d'augmenter votre permission

### Oublié identifiants
→ Contactez l'administrateur (pas de fonction reset disponible)

---

## 📚 Documentation complète

Voir `SETUP.md` pour:
- Installation complète
- Configuration de la BD
- Guide d'utilisation détaillé
- Structure du projet
- Dépannage

