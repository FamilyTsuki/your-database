<section id="page-ajout" style="display:none;">
    <div class="form-card">
        <h2>Nouvel Objet</h2>
        <form action="ajouter.php" method="POST" enctype="multipart/form-data">
            <label>Photo de l'objet</label>
            <input type="file" name="image" id="imageInput" accept="image/*" capture="environment" style="display:none;" onchange="previewImage(event)">
            <label for="imageInput" class="upload-placeholder" id="drop-zone">
                <div id="placeholder-content">
                    <span>📷</span>
                    <p>Prendre une photo / Galerie</p>
                </div>
                <img id="imagePreview" src="#" style="display:none; max-width:100%; max-height:100%; border-radius:12px; object-fit: cover;">
            </label>

            <label>Nom de l'objet</label>
            <input type="text" name="nom" required placeholder="Ex: Marteau">
            
            <label>Catégorie</label>
            <div class="category-group">
                <select name="categorie" id="categorySelect" onchange="checkNewCategory(this)">
                    <option value="">-- Choisir une catégorie --</option>
                    <?php
                    $catRes = $conn->query("SELECT DISTINCT categorie FROM objets WHERE categorie != '' ORDER BY categorie ASC");
                    while($c = $catRes->fetch_assoc()) {
                        echo "<option value='".htmlspecialchars($c['categorie'])."'>".$c['categorie']."</option>";
                    }
                    ?>
                    <option value="NEW" style="font-weight: bold; color: #3498db;">+ Ajouter une nouvelle catégorie</option>
                </select>
                <input type="text" id="newCategoryInput" placeholder="Nom de la nouvelle catégorie" style="display:none; margin-top: 10px;">
            </div>

            <label>Quantité initiale</label>
            <input type="number" name="quantite" value="1" min="1">
            
            <?php echo CsrfToken::field(); ?>
            
            <button type="submit" class="btn-save">ENREGISTRER</button>
        </form>
    </div>
</section>
