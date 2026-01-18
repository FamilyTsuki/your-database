<div class="container">
    <?php echo FlashMessage::render(); ?>
    
    <section id="page-consultation">
        <div class="search-zone">
            <input type="text" id="searchInput" onkeyup="filterItems()" placeholder="Rechercher un objet...">
            <select id="categoryFilter" onchange="filterItems()">
                <option value="">Toutes les catégories</option>
                <?php
                $catRes = $conn->query("SELECT DISTINCT categorie FROM objets WHERE categorie != ''");
                while($c = $catRes->fetch_assoc()) {
                    echo "<option value='".htmlspecialchars($c['categorie'])."'>".$c['categorie']."</option>";
                }
                ?>
            </select>
        </div>

        <div class="grid" id="inventoryGrid">
            <?php
            $res = $conn->query("SELECT * FROM objets ORDER BY id DESC");
            while($row = $res->fetch_assoc()):
                $hasImg = !empty($row['image_path']);
            ?>
            <div class="card" data-name="<?php echo strtolower(htmlspecialchars($row['nom'])); ?>" data-cat="<?php echo htmlspecialchars($row['categorie']); ?>">
                
                <?php if($hasImg): ?>
                    <img src="uploads/<?php echo $row['image_path']; ?>" onclick="changeImage(<?php echo $row['id']; ?>)" alt="Photo objet">
                <?php else: ?>
                    <div class="card-no-image" onclick="changeImage(<?php echo $row['id']; ?>)">
                        <span>📷</span>
                        <p>Ajouter photo</p>
                    </div>
                <?php endif; ?>

                <div class="card-details">
                    <h3 style="cursor:pointer; border-bottom: 1px dashed rgba(0,0,0,0.1); " 
                        onclick="editField(<?php echo $row['id']; ?>, 'nom', '<?php echo addslashes($row['nom']); ?>')" 
                        title="Renommer l'objet">
                        <?php echo htmlspecialchars($row['nom']); ?>
                    </h3>
                    
                    <span class="tag" style="cursor:pointer;" onclick="editField(<?php echo $row['id']; ?>, 'categorie', '<?php echo addslashes($row['categorie']); ?>')" title="Modifier la catégorie">
                        <?php echo htmlspecialchars($row['categorie']); ?>
                    </span>
                    
                    <div class="qty-zone">
                        <a href="update.php?id=<?php echo $row['id']; ?>&action=dec" class="btn-qty">-</a>
                        <span class="qty-val" style="cursor:pointer; border-bottom: 2px dashed #3498db;" onclick="editField(<?php echo $row['id']; ?>, 'quantite', <?php echo $row['quantite']; ?>)" title="Modifier la quantité">
                            <?php echo $row['quantite']; ?>
                        </span>
                        <a href="update.php?id=<?php echo $row['id']; ?>&action=inc" class="btn-qty">+</a>
                    </div>
                    
                    <a href="update.php?id=<?php echo $row['id']; ?>&action=delete" class="delete-link" onclick="return confirm('Supprimer cet objet ?')">🗑 Supprimer</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </section>

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