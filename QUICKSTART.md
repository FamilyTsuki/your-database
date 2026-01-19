# 🚀 Guide de Démarrage Rapide

## ⚡ Installation en 5 minutes

### Étape 1: Vérifier les prérequis
```
✓ PHP 7.4+
✓ MySQL/MariaDB en cours d'exécution
✓ Apache/Nginx configuré
```

### Étape 2: Initialiser la base de données

Choisissez l'une des options:

#### Option A: Via le navigateur (Recommandée) 🌐
1. Accédez à `http://localhost/your-database/public/setup.php`
2. Cliquez sur "Initialiser la base de données"
3. ✅ Prêt!

#### Option B: Via phpMyAdmin
1. Allez à phpMyAdmin
2. Créez une base `maison_db`
3. Allez dans l'onglet "SQL"
4. Collez le contenu de `setup_databases.sql`
5. Exécutez

#### Option C: Ligne de commande (Linux/Mac)
```bash
cd /chemin/vers/your-database
bash init_db.sh
```

#### Option D: PowerShell (Windows)
```powershell
cd C:\xampp\htdocs\your-database
powershell -ExecutionPolicy Bypass -File init_db.ps1
```

### Étape 3: Accéder à l'application

1. **Inscription**: `http://localhost/your-database/public/register.php`
   - Créez un compte utilisateur
   
2. **Connexion**: `http://localhost/your-database/public/login.php`
   - Connectez-vous avec vos identifiants

3. **Dashboard**: `http://localhost/your-database/public/index.php`
   - Vous êtes prêt à commencer! 🎉

---

## 🧪 Vérifier que tout fonctionne

Accédez à `http://localhost/your-database/public/test.php`

Vous devriez voir:
- ✅ Connexion à la base de données
- ✅ Table users
- ✅ Table databases  
- ✅ Table database_permissions
- ✅ Table objets
- ✅ Toutes les classes chargées

---

## 📖 Utilisation basique

### 1️⃣ Créer une base de données
- Cliquez "+ Créer une nouvelle base"
- Entrez un nom (ex: "Garage")
- Optionnel: Ajoutez une description
- ✅ Vous êtes propriétaire!

### 2️⃣ Ajouter des objets
- Cliquez "Consulter" sur votre base
- Cliquez "+ Ajouter un objet"
- Remplissez les champs:
  - **Nom**: Ex: "Perceuse"
  - **Catégorie**: Ex: "Outils"
  - **Quantité**: Nombre d'exemplaires
- ✅ Objet ajouté!

### 3️⃣ Partager avec d'autres
- Allez dans "Paramètres" (propriétaire)
- Section "Partage d'accès"
- Entrez le pseudo de la personne
- Choisissez le niveau d'accès:
  - 👁️ **Lecture seule**: Juste regarder
  - ✏️ **Modifier**: Gérer les objets
  - 🔐 **Admin**: Tout contrôler
- ✅ Accès accordé!

### 4️⃣ Gérer les paramètres
**Propriétaire uniquement:**
- Renommer la base
- Modifier la description
- Gérer qui a accès
- Renommer les catégories
- Supprimer la base

---

## 🎯 Cas d'usage

### Garage
- Outil 1, Outil 2, Outil 3...
- Peinture, Vis, Boulons...
- Quantités disponibles

### Cuisine
- Épices et assaisonnements
- Équipements
- Conserves

### Bureau
- Fournitures
- Documents
- Équipement informatique

---

## ❓ FAQ

**Q: Où se trouvent mes données?**
A: Dans la base MySQL `maison_db`, tables `objets` et `databases`

**Q: Puis-je avoir plusieurs bases?**
A: Oui! Créez autant de bases que vous voulez

**Q: Puis-je partager une base avec quelqu'un?**
A: Oui! Via les paramètres, onglet "Partage d'accès"

**Q: Qu'est-ce qui se passe si je supprime une base?**
A: Tous les objets et permissions sont supprimés (irréversible!)

**Q: Peut-on revenir en arrière?**
A: Non. Faites une sauvegarde avant de supprimer.

---

## 🔗 Liens rapides

| Page | URL | Accès |
|------|-----|-------|
| Dashboard | `/index.php` | Connecté |
| Inscription | `/register.php` | Tous |
| Connexion | `/login.php` | Tous |
| Initialisation | `/setup.php` | Connecté |
| Tests | `/test.php` | Tous |

---

## 📞 Support

1. **Consultez** `SETUP.md` pour plus de détails
2. **Consultez** `REFACTORING.md` pour l'architecture
3. **Consultez** `CHANGELOG.md` pour les changements
4. **Vérifiez** `test.php` pour les erreurs
5. **Contactez** l'administrateur système

---

## ✅ Prochaines étapes

1. ✓ Installation complétée
2. ✓ Compte créé
3. → Créer votre première base
4. → Ajouter des objets
5. → Partager avec d'autres
6. → Gérer les permissions

**Bon inventaire! 📦**
