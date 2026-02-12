<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';
require_once __DIR__ . '/../src/Helpers/CsrfToken.php';
require_once __DIR__ . '/../src/Helpers/FlashMessage.php';
require_once __DIR__ . '/../src/Models/DatabaseModel.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Protect route
if (!Auth::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$database_id = intval($_GET['id'] ?? 0);
if (!$database_id) {
    FlashMessage::error('error', 'Base de données non trouvée');
    header('Location: index.php');
    exit;
}

$db_model = new DatabaseModel($conn);
$user_id = $_SESSION['user_id'] ?? null;
$permission = $db_model->getPermission($database_id, $user_id);
if (!$permission) {
    FlashMessage::error('error', 'Accès refusé à cette base de données');
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM `databases` WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $database_id);
$stmt->execute();
$db_info = $stmt->get_result()->fetch_assoc();

if (!$db_info) {
    FlashMessage::error('error', 'Base de données non trouvée');
    header('Location: index.php');
    exit;
}

// Get objects
$stmt = $conn->prepare("SELECT objets.*, cat.nom AS nom_categorie, cat.parent_id, parent_cat.nom AS parent_nom FROM objets LEFT JOIN categories AS cat ON objets.id_categorie = cat.id LEFT JOIN categories AS parent_cat ON cat.parent_id = parent_cat.id WHERE objets.database_id = ? ORDER BY objets.id DESC");
$stmt->bind_param("i", $database_id);
$stmt->execute();
$objets_res = $stmt->get_result();
$objets = [];
while ($row = $objets_res->fetch_assoc()) $objets[] = $row;

// Get categories
$stmt = $conn->prepare("SELECT id, nom, parent_id FROM categories WHERE database_id = ? OR database_id IS NULL ORDER BY parent_id ASC, nom ASC");
$stmt->bind_param("i", $database_id);
$stmt->execute();
$cat_res = $stmt->get_result();
$categories_raw = [];
while ($row = $cat_res->fetch_assoc()) $categories_raw[] = $row;

// Build tree
$categories_tree = [];
foreach ($categories_raw as $cat) {
    if ($cat['parent_id'] == null) { $cat['subs'] = []; $categories_tree[$cat['id']] = $cat; }
    else { if (isset($categories_tree[$cat['parent_id']])) $categories_tree[$cat['parent_id']]['subs'][] = $cat; }
}

$csrf_token = CsrfToken::generate();

include __DIR__ . '/../templates/includes/header.phtml';
?>

<div class="database-view-container">
    <h1><?php echo htmlspecialchars($db_info['name'] ?? 'Base de données'); ?></h1>
    <p class="db-description"><?php echo htmlspecialchars($db_info['description'] ?? ''); ?></p>

    <?php include __DIR__ . '/../templates/consultation.phtml'; ?>
</div>

<script>
window.globalCategories = <?php echo json_encode(array_values($categories_tree)); ?>;
window.csrfToken = '<?php echo $csrf_token; ?>';
window.databaseId = <?php echo json_encode(intval($database_id)); ?>;
<?php $user_prefs = Auth::getUser(); ?>
window.dbCameraEnabled = <?php echo json_encode((bool)($db_info['camera_enabled'] ?? true)); ?>;
window.dbSkipSourceModal = <?php echo json_encode((bool)($user_prefs['skip_source_modal'] ?? false)); ?>;
window.dbPreferGallery = <?php echo json_encode((bool)($user_prefs['prefer_gallery'] ?? false)); ?>;
window.userPermission = <?php echo json_encode($permission); ?>;
</script>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>
