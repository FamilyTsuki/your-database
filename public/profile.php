<?php
require_once '../config/config.php';

if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user = Auth::getUser();
$csrf_token = CsrfToken::generate();

include __DIR__ . '/../templates/includes/header.phtml';
?>
<script>window.csrfToken = <?php echo json_encode($csrf_token); ?>;</script>

<div class="container-narrow">
    <div class="settings-header">
        <h1>👤 Mon Profil</h1>
    </div>

    <div class="settings-section">
        <form id="profileForm" enctype="multipart/form-data">
            <div class="profile-header">
                <div class="profile-avatar-container" id="profileAvatarContainer">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Avatar" class="profile-avatar" id="profileAvatarPreview">
                    <?php else: ?>
                        <div class="profile-avatar profile-avatar-placeholder" id="profileAvatarPreview">👤</div>
                    <?php endif; ?>
                    
                    <label for="profileImageInput" class="profile-avatar-edit">
                        ✏️
                    </label>
                    <input type="file" name="profile_image" id="profileImageInput" accept="image/*" style="display: none;">
                </div>
                <p class="text-muted-small">Cliquez sur le crayon pour changer votre photo</p>
            </div>

            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Adresse Email</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <button type="submit" class="btn-primary margin-top-20">Enregistrer les modifications</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>