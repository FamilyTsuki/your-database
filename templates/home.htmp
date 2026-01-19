<div class="container">
    <?php echo FlashMessage::render(); ?>
    
    <?php include 'consultation.php'; ?>
    <?php include 'ajout.php'; ?>
</div>

<form id="formUpdateImg" action="update_img.php" method="POST" enctype="multipart/form-data" style="display:none;">
    <input type="hidden" name="id" id="updateImgId">
    <input type="file" name="new_image" id="updateImgInput" accept="image/*" capture="environment" onchange="document.getElementById('formUpdateImg').submit()">
</form>

<form id="formFastUpdate" action="fast_update.php" method="POST" style="display:none;">
    <input type="hidden" name="id" id="fastUpdateId">
    <input type="hidden" name="field" id="fastUpdateField">
    <input type="hidden" name="value" id="fastUpdateValue">
    <input type="hidden" name="csrf_token" id="fastUpdateCsrf" value="<?php echo htmlspecialchars(CsrfToken::get(), ENT_QUOTES, 'UTF-8'); ?>">
</form>