<script>
        function showPage(id) {
            document.getElementById('page-consultation').style.display = (id === 'consultation') ? 'block' : 'none';
            document.getElementById('page-ajout').style.display = (id === 'ajout') ? 'block' : 'none';
        }

        function editField(id, field, currentValue) {
            let msg = "";
            let title = "";
            
            if (field === 'quantite') {
                msg = "Nouvelle quantité :";
                title = "Doit être un nombre";
            }
            else if (field === 'categorie') {
                msg = "Nouvelle catégorie :";
                title = "Ex: Cuisine, Jardin...";
            }
            else if (field === 'nom') {
                msg = "Nouveau nom de l'objet :";
                title = "Ex: Marteau, Vis...";
            }

            let newValue = prompt(msg, currentValue);
            
            if (newValue !== null && newValue.trim() !== "") {
                document.getElementById('fastUpdateId').value = id;
                document.getElementById('fastUpdateField').value = field;
                document.getElementById('fastUpdateValue').value = newValue;
                document.getElementById('formFastUpdate').submit();
            }
        }

        function previewImage(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Vérifier la taille (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('L\'image est trop volumineuse (max 5MB)');
                event.target.value = '';
                return;
            }

            // Vérifier le type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Format d\'image non autorisé. Utilisez JPG, PNG, WEBP ou GIF');
                event.target.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById('imagePreview');
                const content = document.getElementById('placeholder-content');
                output.src = reader.result;
                output.style.display = "block";
                content.style.display = "none";
                document.getElementById('drop-zone').style.border = "none";
            }
            reader.readAsDataURL(file);
        }

        function filterItems() {
            let search = document.getElementById('searchInput').value.toLowerCase();
            let cat = document.getElementById('categoryFilter').value;
            let cards = document.querySelectorAll('.card');

            cards.forEach(card => {
                let nameMatch = card.getAttribute('data-name').includes(search);
                let catMatch = (cat === "" || card.getAttribute('data-cat') === cat);
                card.style.display = (nameMatch && catMatch) ? "flex" : "none";
            });
        }

        function changeImage(id) {
            document.getElementById('updateImgId').value = id;
            document.getElementById('updateImgInput').click();
        }

        function checkNewCategory(select) {
            const newCatInput = document.getElementById('newCategoryInput');
            if (select.value === "NEW") {
                newCatInput.style.display = "block";
                newCatInput.name = "categorie";
                newCatInput.required = true;
                newCatInput.focus();
                select.name = ""; 
            } else {
                newCatInput.style.display = "none";
                newCatInput.name = "";
                newCatInput.required = false;
                select.name = "categorie";
            }
        }

        // Fermer les messages flash au clic
        document.addEventListener('DOMContentLoaded', function() {
            const flashMessages = document.querySelectorAll('.flash-message button');
            flashMessages.forEach(btn => {
                btn.addEventListener('click', function() {
                    this.parentElement.style.display = 'none';
                });
            });
        });
    </script>
</body>
</html>