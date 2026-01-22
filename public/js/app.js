function showPage(id) {
  document.getElementById("page-consultation").style.display =
    id === "consultation" ? "block" : "none";
  document.getElementById("page-ajout").style.display =
    id === "ajout" ? "block" : "none";
}

/**
 * Édite un champ (nom, catégorie, quantité)
 */
function editField(id, field, currentValue) {
  let msg = "";
  let title = "";

  if (field === "quantite") {
    msg = "Nouvelle quantité :";
    title = "Doit être un nombre";
  } else if (field === "categorie") {
    // On simule l'événement pour la compatibilité avec editFieldderoul
    editFieldderoul(id, "id_categorie", currentValue, {
      currentTarget: event.target,
    });
    return;
  } else if (field === "nom") {
    msg = "Nouveau nom de l'objet :";
    title = "Ex: Marteau, Vis...";
  }

  let newValue = prompt(msg, currentValue);

  if (newValue !== null && newValue.trim() !== "") {
    document.getElementById("fastUpdateId").value = id;
    document.getElementById("fastUpdateField").value = field;
    document.getElementById("fastUpdateValue").value = newValue;
    document.getElementById("formFastUpdate").submit();
  }
}

/**
 * Affiche un aperçu de l'image avant upload
 */
function previewImage(event) {
  const file = event.target.files[0];
  if (!file) return;

  // Vérifier la taille (max 5MB)
  if (file.size > 5 * 1024 * 1024) {
    alert("L'image est trop volumineuse (max 5MB)");
    event.target.value = "";
    return;
  }

  // Vérifier le type
  const allowedTypes = ["image/jpeg", "image/png", "image/webp", "image/gif"];
  if (!allowedTypes.includes(file.type)) {
    alert("Format d'image non autorisé. Utilisez JPG, PNG, WEBP ou GIF");
    event.target.value = "";
    return;
  }

  const reader = new FileReader();
  reader.onload = function () {
    const output = document.getElementById("imagePreview");
    const content = document.getElementById("placeholder-content");
    output.src = reader.result;
    output.style.display = "block";
    content.style.display = "none";
    document.getElementById("drop-zone").style.border = "none";
  };
  reader.readAsDataURL(file);
}

/**
 * Filtre les items selon la recherche et catégorie
 */
function filterItems() {
  let search = document.getElementById("searchInput").value.toLowerCase();
  let cat = document.getElementById("categoryFilter").value; // Récupère le NOM sélectionné
  let cards = document.querySelectorAll(".card");

  cards.forEach((card) => {
    let nameMatch = card.getAttribute("data-name").includes(search);
    // On compare le NOM de la catégorie
    let catMatch = cat === "" || card.getAttribute("data-cat") === cat;
    card.style.display = nameMatch && catMatch ? "flex" : "none";
  });
}

/**
 * Ouvre le sélecteur d'image pour une fiche
 */
function changeImage(id) {
  document.getElementById("updateImgId").value = id;
  document.getElementById("updateImgInput").click();
}

/**
 * Gère l'ajout de nouvelle catégorie
 */
function checkNewCategory(select) {
  const newCatInput = document.getElementById("newCategoryInput");
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

/**
 * Flag pour éviter les clics multiples rapides
 */
let isUpdatingQuantity = false;

/**
 * Mettre à jour la quantité sans recharger la page
 */
function updateQuantity(id, action) {
  if (isUpdatingQuantity) return;
  isUpdatingQuantity = true;

  // On récupère l'élément HTML qui affiche le chiffre
  const qtySpan = document.getElementById("qty-" + id);
  const currentQty = parseInt(qtySpan.textContent);
  const newQty =
    action === "inc" ? currentQty + 1 : Math.max(0, currentQty - 1);

  fetch("update.php?id=" + id + "&action=" + action)
    .then((response) => {
      isUpdatingQuantity = false;
      if (response.ok) {
        // Mise à jour visuelle immédiate SANS recharger la page
        qtySpan.textContent = newQty;

        // On met à jour l'attribut onclick de l'élément pour que
        // le prochain editField ait la bonne valeur par défaut
        qtySpan.setAttribute(
          "onclick",
          `editField(${id}, 'quantite', ${newQty})`,
        );
      }
    })
    .catch((error) => {
      isUpdatingQuantity = false;
      alert("Erreur lors de la mise à jour");
    });
}

/**
 * Gère la disparition automatique des messages flash
 */
window.addEventListener("load", function () {
  const flashMessages = document.querySelectorAll(".flash-message");

  flashMessages.forEach((msg) => {
    // Fermeture au clic du bouton X
    const btn = msg.querySelector("button");
    if (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        msg.classList.add("fade-out");
        setTimeout(() => (msg.style.display = "none"), 500);
      });
    }

    // Disparition automatique après 4 secondes
    setTimeout(() => {
      if (msg.style.display !== "none") {
        msg.classList.add("fade-out");
        setTimeout(() => (msg.style.display = "none"), 500);
      }
    }, 4000);
  });
});
