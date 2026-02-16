<?php
require_once '../config/config.php';

if (!Auth::isLoggedIn()) {
    header("Location: login");
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

            <div class="settings-section" style="margin-top: 30px; padding: 0; border: none; background: none;">
                <h3 style="margin-bottom: 15px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">Préférences</h3>
                
                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between;">
                    <label for="redirect_on_add" style="margin: 0;">Rediriger vers la liste après un ajout</label>
                    <label class="switch">
                        <input type="checkbox" name="redirect_on_add" id="redirect_on_add" value="1" <?php echo ($user['redirect_on_add'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                </div>

                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between;">
                    <label for="skip_source_modal" style="margin: 0;">Ne pas demander la source (ouvrir directement)</label>
                    <label class="switch">
                        <input type="checkbox" name="skip_source_modal" id="skip_source_modal" value="1" <?php echo ($user['skip_source_modal'] ?? 0) ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                </div>

                <div class="form-group margin-left-20" id="prefer_gallery_group" style="display:none; flex-direction: row; align-items: center; justify-content: space-between; margin-left: 20px; padding-left: 10px; border-left: 2px solid var(--border);">
                    <label for="prefer_gallery" style="margin: 0;">Toujours utiliser la galerie (sinon Caméra)</label>
                    <label class="switch">
                        <input type="checkbox" name="prefer_gallery" id="prefer_gallery" value="1" <?php echo ($user['prefer_gallery'] ?? 0) ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                </div>

                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                    <label for="dark_mode" style="margin: 0;">Mode Sombre</label>
                    <label class="switch"><input type="checkbox" name="dark_mode" id="dark_mode" value="1" <?php echo ($user['dark_mode'] ?? 0) ? 'checked' : ''; ?>><span class="slider round"></span></label>
                </div>
            </div>

            <button type="submit" class="btn-primary margin-top-20">Enregistrer les modifications</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const skipCheck = document.getElementById('skip_source_modal');
    const galleryGroup = document.getElementById('prefer_gallery_group');
    const toggle = () => {
        if(skipCheck && galleryGroup) galleryGroup.style.display = skipCheck.checked ? 'flex' : 'none';
    };
    if(skipCheck) { skipCheck.addEventListener('change', toggle); toggle(); }

    // Gestion du formulaire de profil via AJAX
    const form = document.getElementById('profileForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'update_profile');
            formData.append('csrf_token', window.csrfToken);

            fetch('api/user', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Appliquer le thème confirmé
                    if (data.user.dark_mode == 1) {
                        document.body.classList.add('dark-mode');
                    } else {
                        document.body.classList.remove('dark-mode');
                    }
                    
                    // Message de succès
                    showToast('Profil et préférences enregistrés !');
                } else {
                    showToast(data.error || data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(err => console.error(err));
        });
    }

    // Aperçu immédiat du Mode Sombre
    const darkModeToggle = document.getElementById('dark_mode');
    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', function() {
            if (this.checked) document.body.classList.add('dark-mode');
            else document.body.classList.remove('dark-mode');
        });
    }
});
</script>
<?php include __DIR__ . '/../templates/includes/footer.html'; ?>