<?php
require_once '../config/config.php';

if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$database_id = intval($_GET['id'] ?? 0);
$user_id = Auth::getUserId();

$db_controller = new DatabaseController($conn);
$permission = $db_controller->getPermission($database_id, $user_id);

if (!$permission) {
    die("Accès refusé");
}

$database = $db_controller->getDatabase($database_id);
if (!$database) {
    die("Base introuvable");
}

// Récupération des objets avec leurs catégories
$sql = "SELECT objets.*, cat.nom AS nom_categorie, parent_cat.nom AS parent_nom 
        FROM objets 
        LEFT JOIN categories AS cat ON objets.id_categorie = cat.id 
        LEFT JOIN categories AS parent_cat ON cat.parent_id = parent_cat.id 
        WHERE objets.database_id = ? 
        ORDER BY objets.id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $database_id);
$stmt->execute();
$result = $stmt->get_result();

// Configuration du téléchargement CSV
$filename = "inventaire_" . preg_replace('/[^a-z0-9]+/', '_', strtolower($database['name'])) . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Ouverture du flux de sortie
$output = fopen('php://output', 'w');

// Ajout du BOM UTF-8 pour que Excel affiche correctement les accents
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes des colonnes
fputcsv($output, ['ID', 'Nom', 'Catégorie', 'Sous-catégorie', 'Quantité', 'Modèle', 'Position', 'Description', 'Lien achat', 'Utilisé', 'Dégradé', 'Image'], ';');

while ($row = $result->fetch_assoc()) {
    $category = $row['parent_nom'] ? $row['parent_nom'] : ($row['nom_categorie'] ?: 'Sans catégorie');
    $subcategory = $row['parent_nom'] ? $row['nom_categorie'] : '';
    
    fputcsv($output, [
        $row['id'],
        $row['nom'],
        $category,
        $subcategory,
        $row['quantite'],
        $row['model'],
        $row['position'],
        $row['description'],
        $row['purchase_link'],
        $row['qty_used'],
        $row['qty_degraded'],
        $row['image_path'] ? 'uploads/' . $row['image_path'] : ''
    ], ';');
}

fclose($output);
exit();