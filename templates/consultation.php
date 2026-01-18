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
                    <button class="btn-qty" onclick="updateQuantity(<?php echo $row['id']; ?>, 'dec')">-</button>
                    <span class="qty-val" id="qty-<?php echo $row['id']; ?>" style="cursor:pointer; border-bottom: 2px dashed #3498db;" onclick="editField(<?php echo $row['id']; ?>, 'quantite', <?php echo $row['quantite']; ?>)" title="Modifier la quantité">
                        <?php echo $row['quantite']; ?>
                    </span>
                    <button class="btn-qty" onclick="updateQuantity(<?php echo $row['id']; ?>, 'inc')">+</button>
                </div>
                
                <a href="update.php?id=<?php echo $row['id']; ?>&action=delete" class="delete-link" onclick="return confirm('Supprimer cet objet ?')">🗑 Supprimer</a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</section>
