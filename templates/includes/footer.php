<script>
        function showPage(id) {
            document.getElementById('page-consultation').style.display = (id === 'consultation') ? 'block' : 'none';
            document.getElementById('page-ajout').style.display = (id === 'ajout') ? 'block' : 'none';
        }

        function editField(id, field, currentValue) {
            let msg = "";
            if (field === 'quantite') msg = "Nouvelle quantité :";
            else if (field === 'categorie') msg = "Nouvelle catégorie :";
            else if (field === 'nom') msg = "Nouveau nom de l'objet :";

            let newValue = prompt(msg, currentValue);
            
            if (newValue !== null && newValue.trim() !== "") {
                document.getElementById('fastUpdateId').value = id;
                document.getElementById('fastUpdateField').value = field;
                document.getElementById('fastUpdateValue').value = newValue;
                document.getElementById('formFastUpdate').submit();
            }
        }

        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById('imagePreview');
                const content = document.getElementById('placeholder-content');
                output.src = reader.result;
                output.style.display = "block";
                content.style.display = "none";
                document.getElementById('drop-zone').style.border = "none";
            }
            reader.readAsDataURL(event.target.files[0]);
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
    </script>
</body>
</html>