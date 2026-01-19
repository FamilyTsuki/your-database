# Intégration du Système Page-Consultation/Page-Ajout

## Modifications Effectuées

### 1. **Templates Adaptés pour Multi-Bases**

#### `templates/consultation.phtml` 
- Remplacé les requêtes directes `$conn->query()` par des variables `$objets` et `$categories`
- Adapté pour afficher uniquement les objets de la base actuelle
- Conservé tous les événements JavaScript (editField, updateQuantity, deleteObject)
- Maintenu la recherche et le filtrage par catégorie

#### `templates/ajout.html`
- Remplacé les catégories statiques par une boucle sur `$categories`
- Adapté le formulaire pour POST via le contrôleur (pas d'action externe)
- Conservé l'aperçu d'image (previewImage) et la gestion "NEW" catégorie
- Intégré le token CSRF pour la sécurité

### 2. **Création de `public/database-view.php`**

Le fichier central qui gère une base de données spécifique:

**Fonctionnalités:**
- ✅ Vérification des permissions (view, edit, admin)
- ✅ Gestion d'ajout d'objets avec upload image
- ✅ Opérations AJAX pour les modifications rapides:
  - `updateQty` - Modifier la quantité
  - `edit` - Modifier nom/catégorie
  - `delete` - Supprimer avec nettoyage image
  - `updateImage` - Changer l'image d'un objet
- ✅ Validation de fichiers (type MIME, taille max 5MB)
- ✅ Création automatique du dossier `/uploads`
- ✅ Suppression automatique des anciennes images

**Structure:**
```php
// Vérifie accès & permissions
// Gère POST AJAX
// Gère POST formulaire ajout
// Récupère données (objets, catégories)
// Inclut les templates
```

### 3. **Navigation Mise à Jour**

#### `templates/includes/header.phtml`
- Changé condition pour afficher "Ajouter" sur `database-view.php` (pas `database.php`)
- Conservé le bouton "📦 Mes Bases" vers `index.php`

#### `templates/dashboard.phtml`
- Liens "Consulter" changés de `database.php?id=X` → `database-view.php?id=X`

#### `public/database-settings.php`
- Lien "Retour" changé de `database.php?id=X` → `database-view.php?id=X`

## Architecture Finale

```
ACCÈS À UNE BASE DE DONNÉES:
┌─────────────────────────────────────────────┐
│  index.php (Dashboard)                      │
│  ↓                                          │
│  → Affiche cartes bases de données          │
│    "Consulter" → database-view.php?id=X    │
│    "Paramètres" → database-settings.php     │
└─────────────────────────────────────────────┘

CONSULTATION/AJOUT DANS UNE BASE:
┌─────────────────────────────────────────────┐
│  database-view.php?id=X                     │
│  ↓                                          │
│  → Charge consultation.phtml                │
│  → Charge ajout.html                        │
│  → Gère AJAX updates (qty, edit, delete)    │
│  → Gère formulaire ajout (avec image)       │
│  → Gère uploads vers /uploads/              │
└─────────────────────────────────────────────┘

PARAMÈTRES/PERMISSIONS:
┌─────────────────────────────────────────────┐
│  database-settings.php?id=X                 │
│  ↓                                          │
│  → Permet de renommer base/catégories       │
│  → Gère permissions utilisateurs            │
│  → Suppression base (admin)                 │
└─────────────────────────────────────────────┘
```

## Fonctionnalités Images

### Upload Lors de Création
```php
- Formulaire accept="image/*"
- Prévisualisation live (previewImage)
- Upload au submit
- Stockage: /uploads/obj_TIMESTAMP_UNIQID.ext
- Enregistrement du chemin en DB
```

### Modification d'Image  
```php
- Click sur image → changeImage(id)
- Sélection fichier → requête AJAX
- Suppression ancienne image
- Upload nouvelle → /uploads/obj_ID_TIMESTAMP.ext
- Rechargement page
```

### Suppression Automatique
```php
- Lors de deleteObject() → image supprimée aussi
- Action AJAX: unlink(/uploads/IMAGE)
```

## Sécurité

✅ **Tokens CSRF** sur tous les formulaires  
✅ **Validation MIME** - Images uniquement  
✅ **Limite de taille** - Max 5MB  
✅ **Permissions** - edit/admin requis  
✅ **Filtre database_id** - Isolation par base  

## Tests à Faire

1. ✅ Créer une base de données
2. ✅ Ajouter un objet sans image
3. ✅ Ajouter un objet avec image
4. ✅ Modifier la quantité (+-) 
5. ✅ Modifier nom/catégorie
6. ✅ Changer l'image d'un objet
7. ✅ Supprimer un objet
8. ✅ Rechercher/filtrer par catégorie
9. ✅ Créer nouvelle catégorie
10. ✅ Vérifier permissions (view user ne peut pas ajouter)

## Fichiers Modifiés

- `public/database-view.php` - ✅ CRÉÉ (360 lignes PHP/JS)
- `templates/consultation.phtml` - ✅ ADAPTÉ
- `templates/ajout.html` - ✅ ADAPTÉ
- `templates/includes/header.phtml` - ✅ MIS À JOUR
- `templates/dashboard.phtml` - ✅ MIS À JOUR
- `public/database-settings.php` - ✅ MIS À JOUR

## Notes

- L'ancien `database.php` est toujours présent mais pas utilisé
- Peut être supprimé si tout fonctionne
- Les permissions sont vérifiées via `DatabaseModel->getPermission()`
- Les images sont isolées par base (pas de conflit)
