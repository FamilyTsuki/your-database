// public/js/app.js — consolidated client-side application
// Front-end logic migrated from server templates to a single client
// This file centralizes API calls and UI rendering for the inventory app.

function showPage(id) {
  const c = document.getElementById("page-consultation");
  const a = document.getElementById("page-ajout");
  if (c) c.style.display = id === "consultation" ? "block" : "none";
  if (a) a.style.display = id === "ajout" ? "block" : "none";
}

async function apiPost(data) {
  const params = new URLSearchParams();
  for (const k in data) params.append(k, data[k]);
  const res = await fetch("api/database.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: params.toString(),
  });
  return res.json();
}

/**
 * Affiche un message flash (succès ou erreur)
 */
function showFlash(message, type = "info") {
  const div = document.createElement("div");
  div.className = `flash-message flash-${type}`;

  const text = document.createElement("span");
  text.textContent = message;

  const btn = document.createElement("button");
  btn.innerHTML = "&times;";
  btn.style.position = "absolute";
  btn.style.top = "50%";
  btn.style.right = "10px";
  btn.style.transform = "translateY(-50%)";
  btn.style.background = "none";
  btn.style.border = "none";
  btn.style.fontSize = "20px";
  btn.style.cursor = "pointer";
  btn.style.color = "inherit";

  btn.onclick = () => {
    div.classList.add("fade-out");
    setTimeout(() => div.remove(), 500);
  };

  div.appendChild(text);
  div.appendChild(btn);
  document.body.appendChild(div);

  // Auto remove
  setTimeout(() => {
    if (document.body.contains(div)) {
      div.classList.add("fade-out");
      setTimeout(() => {
        if (document.body.contains(div)) div.remove();
      }, 500);
    }
  }, 4000);
}

/**
 * Édite un champ (nom, quantité) via prompt et envoie à l'API
 */
function editField(id, field, currentValue) {
  if (field === "id_categorie" || field === "categorie") return;
  const newVal = prompt("Modifier " + field + ":", currentValue);
  if (newVal === null || newVal.trim() === "") return;
  apiPost({
    action: "edit",
    id,
    field,
    value: newVal,
    csrf_token: window.csrfToken,
    database_id: window.databaseId,
  })
    .then((d) => {
      if (d.success) location.reload();
      else
        showFlash(
          "Erreur: " + (d.message || "Impossible de mettre à jour"),
          "error",
        );
    })
    .catch(() => showFlash("Erreur réseau", "error"));
}

/**
 * Affiche un aperçu de l'image avant upload
 */
function previewImage(event) {
  const file = event.target.files[0];
  if (!file) return;

  // Vérifier la taille (max 5MB)
  if (file.size > 5 * 1024 * 1024) {
    showFlash("L'image est trop volumineuse (max 5MB)", "error");
    event.target.value = "";
    return;
  }

  // Vérifier le type
  const allowedTypes = ["image/jpeg", "image/png", "image/webp", "image/gif"];
  if (!allowedTypes.includes(file.type)) {
    showFlash(
      "Format d'image non autorisé. Utilisez JPG, PNG, WEBP ou GIF",
      "error",
    );
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
 * Filtre les items (Déclenche un rechargement serveur avec debounce)
 */
let searchTimeout;
function filterItems() {
  // Debounce pour éviter de spammer l'API à chaque frappe
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    window.currentPage = 1; // Retour à la page 1 quand on change les filtres
    fetchAndRenderInventory();
  }, 300);
}

// État global de la pagination
window.currentPage = 1;
window.itemsPerPage = 50;

/**
 * Ouvre la modale de choix de source (Caméra vs Galerie)
 * @param {Function} onChoice - Callback appelée avec 'camera' ou 'gallery'
 */
function openSourceChoice(onChoice) {
  // Si l'option "Ne pas demander" est activée
  if (window.dbSkipSourceModal) {
    if (window.dbPreferGallery) {
      onChoice("gallery");
    } else {
      onChoice("camera");
    }
    return;
  }

  const modal = document.getElementById("sourceChoiceModal");
  if (!modal) {
    // Fallback si la modale n'est pas trouvée
    onChoice("gallery");
    return;
  }

  const btnCamera = document.getElementById("btnCamera");
  const btnGallery = document.getElementById("btnGallery");
  const btnClose = document.getElementById("closeSourceModal");

  const close = () => {
    modal.style.display = "none";
    document.body.classList.remove("modal-open");
  };

  // Configuration des actions
  btnCamera.onclick = () => {
    close();
    setTimeout(() => onChoice("camera"), 350);
  };

  btnGallery.onclick = () => {
    close();
    setTimeout(() => onChoice("gallery"), 350);
  };

  btnClose.onclick = close;
  modal.onclick = (e) => {
    if (e.target === modal) close();
  };

  // Affichage
  document.body.classList.add("modal-open");
  modal.style.display = "flex";
}

/**
 * Affiche l'image en plein écran
 */
function viewFullImage(src) {
  const modal = document.getElementById("imageViewerModal");
  const img = document.getElementById("fullImage");
  const closeBtn = document.getElementById("closeImageViewer");

  if (modal && img && closeBtn) {
    img.src = src;
    document.body.classList.add("modal-open");
    modal.style.display = "flex";

    const close = () => {
      modal.style.display = "none";
      document.body.classList.remove("modal-open");
    };

    closeBtn.onclick = close;
    modal.onclick = (e) => {
      if (e.target === modal) {
        close();
      }
    };
  }
}

/**
 * Ouvre la modale de détails pour un objet
 */
function openObjectDetails(row) {
  const modal = document.getElementById("objectDetailsModal");
  if (!modal) return;

  // Remplir les champs
  document.getElementById("detailTitle").textContent = row.nom;
  document.getElementById("detailId").value = row.id;
  document.getElementById("detailModel").value = row.model || "";
  document.getElementById("detailPosition").value = row.position || "";
  document.getElementById("detailLink").value = row.purchase_link || "";
  document.getElementById("detailDesc").value = row.description || "";

  const total = parseInt(row.quantite) || 0;
  const used = parseInt(row.qty_used) || 0;
  const degraded = parseInt(row.qty_degraded) || 0;

  document.getElementById("detailQtyTotal").value = total;
  document.getElementById("detailQtyUsed").value = used;
  document.getElementById("detailQtyDegraded").value = degraded;

  const updateAvailable = () => {
    const tInput = document.getElementById("detailQtyTotal");
    const uInput = document.getElementById("detailQtyUsed");
    const dInput = document.getElementById("detailQtyDegraded");

    const t = parseInt(tInput.value) || 0;
    let u = parseInt(uInput.value) || 0;
    let d = parseInt(dInput.value) || 0;

    // Contraintes dynamiques des inputs
    // Le total ne peut pas être inférieur à ce qui est déjà utilisé/HS
    tInput.min = u + d;

    // On ne peut pas déclarer plus d'utilisé que ce qui reste (Total - HS)
    const maxUsed = Math.max(0, t - d);
    uInput.max = maxUsed;
    if (u > maxUsed) {
      u = maxUsed;
      uInput.value = u;
    }

    // On ne peut pas déclarer plus de HS que ce qui reste (Total - Utilisé)
    const maxDegraded = Math.max(0, t - u);
    dInput.max = maxDegraded;
    if (d > maxDegraded) {
      d = maxDegraded;
      dInput.value = d;
    }

    const available = t - u - d;
    const availInput = document.getElementById("detailQtyAvailable");

    if (available < 0) {
      availInput.value = "Erreur (" + available + ")";
      availInput.style.background = "var(--danger)";
    } else {
      availInput.value = available;
      availInput.style.background = "var(--success)";
    }
  };

  document.getElementById("detailQtyTotal").oninput = updateAvailable;
  document.getElementById("detailQtyUsed").oninput = updateAvailable;
  document.getElementById("detailQtyDegraded").oninput = updateAvailable;
  updateAvailable();

  // Gestionnaire de soumission
  const form = document.getElementById("detailsForm");
  form.onsubmit = async (e) => {
    e.preventDefault();

    // Validation avant envoi
    const t = parseInt(document.getElementById("detailQtyTotal").value) || 0;
    const u = parseInt(document.getElementById("detailQtyUsed").value) || 0;
    const d = parseInt(document.getElementById("detailQtyDegraded").value) || 0;
    if (u + d > t) {
      showFlash(
        "Erreur: La somme (Utilisé + Dégradé) dépasse le total !",
        "error",
      );
      return;
    }

    const fd = new FormData(form);
    fd.append("action", "update_full");
    fd.append("csrf_token", window.csrfToken);
    fd.append("database_id", window.databaseId);

    try {
      const res = await fetch("api/database.php", { method: "POST", body: fd });
      const d = await res.json();
      if (d.success) {
        showFlash("Détails enregistrés", "success");
        modal.style.display = "none";
        document.body.classList.remove("modal-open");
        fetchAndRenderInventory(); // Rafraîchir la grille
      } else showFlash("Erreur: " + (d.error || "Erreur sauvegarde"), "error");
    } catch (err) {
      showFlash("Erreur réseau", "error");
    }
  };

  // Close logic
  const closeBtn = document.getElementById("closeDetailsModal");
  const close = () => {
    modal.style.display = "none";
    document.body.classList.remove("modal-open");
  };
  if (closeBtn) closeBtn.onclick = close;
  modal.onclick = (e) => {
    if (e.target === modal) close();
  };

  document.body.classList.add("modal-open");
  modal.style.display = "flex";
}

/**
 * Ouvre le sélecteur d'image pour une fiche
 */
function changeImage(id) {
  openSourceChoice((source) => {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";
    if (source === "camera") {
      input.setAttribute("capture", "environment");
    }

    input.onchange = async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const fd = new FormData();
      fd.append("action", "updateImage");
      fd.append("id", id);
      fd.append("csrf_token", window.csrfToken);
      fd.append("database_id", window.databaseId);
      fd.append("image", file);
      const res = await fetch("api/database.php", { method: "POST", body: fd });
      const d = await res.json();
      if (d.success) location.reload();
      else showFlash("Erreur: " + (d.error || "upload"), "error");
    };
    input.click();
  });
}

// Generic AJAX submit handler for forms marked with data-ajax="true"
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll('form[data-ajax="true"]').forEach((form) => {
    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      const action = form.getAttribute("action") || window.location.href;
      const fd = new FormData(form);
      // ensure CSRF present
      if (!fd.has("csrf_token") && window.csrfToken)
        fd.append("csrf_token", window.csrfToken);
      try {
        const res = await fetch(action, {
          method: "POST",
          body: fd,
          credentials: "same-origin",
        });
        // If server redirected (e.g., login), follow
        if (res.redirected) {
          window.location = res.url;
          return;
        }
        if (res.ok) {
          // On success, reload to reflect server-side changes
          window.location.reload();
        } else {
          const text = await res.text();
          showFlash("Erreur: " + (text || res.statusText), "error");
        }
      } catch (err) {
        showFlash("Erreur réseau", "error");
      }
    });
  });
});

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
window.isUpdatingQuantity = false;

/**
 * Mettre à jour la quantité sans recharger la page
 */
async function updateQuantity(id, action) {
  if (isUpdatingQuantity) return;
  isUpdatingQuantity = true;
  const qtySpan = document.getElementById("qty-" + id);
  const currentQty = parseInt(qtySpan ? qtySpan.textContent : "0");
  const newQty =
    action === "inc" ? currentQty + 1 : Math.max(0, currentQty - 1);
  try {
    const d = await apiPost({
      action: "updateQty",
      id,
      qty: newQty,
      csrf_token: window.csrfToken,
      database_id: window.databaseId,
    });
    if (d.success && qtySpan) qtySpan.textContent = newQty;
    else if (d.error) showFlash(d.error, "error");
  } catch (e) {
    showFlash("Erreur lors de la mise à jour", "error");
  }
  isUpdatingQuantity = false;
}

function deleteObject(id) {
  if (!confirm("Supprimer cet objet ?")) return;
  apiPost({
    action: "delete",
    id,
    csrf_token: window.csrfToken,
    database_id: window.databaseId,
  })
    .then((d) => {
      if (d.success) location.reload();
      else showFlash("Erreur suppression", "error");
    })
    .catch(() => showFlash("Erreur réseau", "error"));
}

function editFieldderoul(id, field, currentValue, event) {
  const target = event && event.currentTarget;
  if (!target) return;
  // Prevent double-opening when the handler is triggered twice
  if (target.dataset.catMenuOpen === "1") return;
  target.dataset.catMenuOpen = "1";
  const parent = target.parentNode;

  // Create container for category tree menu
  const menu = document.createElement("div");
  menu.className = "category-tree-menu";

  // Ensure parent has relative positioning for absolute positioning to work

  if (
    parent &&
    (!parent.style.position || parent.style.position === "static")
  ) {
    parent.style.position = "relative";
  }

  // "Sans catégorie" option
  const optNone = document.createElement("div");
  optNone.className = "cat-option";

  optNone.textContent = "-- Sans catégorie --";
  optNone.addEventListener("click", () => {
    selectCategory("0", "");
  });
  menu.appendChild(optNone);

  // Build category tree
  if (window.globalCategories) {
    window.globalCategories.forEach((p) => {
      // Parent category container
      const parentContainer = document.createElement("div");
      parentContainer.style.cssText = `border-bottom: 1px solid #ecf0f1;`;

      // Parent header with arrow
      const parentHeader = document.createElement("div");
      parentHeader.className = "cat-parent";

      const arrow = document.createElement("span");
      arrow.className = "cat-arrow";
      arrow.textContent = "▼";

      const label = document.createElement("span");
      label.textContent = p.nom;
      label.style.cssText = `flex: 1;`;

      parentHeader.appendChild(arrow);
      parentHeader.appendChild(label);
      parentContainer.appendChild(parentHeader);

      // Children container
      const childrenContainer = document.createElement("div");
      childrenContainer.className = "cat-children";

      let isExpanded = false;

      // Toggle children visibility
      const toggleChildren = () => {
        isExpanded = !isExpanded;
        childrenContainer.style.display = isExpanded ? "block" : "none";
        arrow.style.transform = isExpanded ? "rotate(180deg)" : "rotate(0deg)";
      };

      parentHeader.addEventListener("click", toggleChildren);

      // Add parent as selectable option
      const selectParent = document.createElement("div");

      selectParent.textContent = "Sélectionner: " + p.nom;
      selectParent.addEventListener("click", () => {
        selectCategory(p.id, p.nom);
      });
      childrenContainer.appendChild(selectParent);

      // Add subcategories
      if (p.subs && p.subs.length > 0) {
        p.subs.forEach((s) => {
          const subOption = document.createElement("div");

          subOption.textContent = s.nom;
          subOption.addEventListener("click", () => {
            selectCategory(s.id, s.nom);
          });
          childrenContainer.appendChild(subOption);
        });
      }

      // Add subcategory button
      const addSubBtn = document.createElement("div");
      addSubBtn.className = "btn-add-sub";

      addSubBtn.textContent = "+ New SubCategory";
      addSubBtn.addEventListener("click", () => {
        const newName = prompt(
          'Nom de la nouvelle sous-catégorie sous "' + p.nom + '":',
        );
        if (newName && newName.trim() !== "") {
          apiPost({
            action: "edit",
            id,
            field: "new_subcategory_create",
            value: newName,
            parent_id: p.id,
            csrf_token: window.csrfToken,
            database_id: window.databaseId,
          }).then((d) => {
            if (d.success) location.reload();
            else showFlash("Erreur création sous-catégorie", "error");
          });
        }
      });
      childrenContainer.appendChild(addSubBtn);

      parentContainer.appendChild(childrenContainer);
      menu.appendChild(parentContainer);
    });
  }

  // Add new category button
  const newCatDiv = document.createElement("div");
  newCatDiv.className = "btn-new-cat";

  newCatDiv.textContent = "+ New Category";
  newCatDiv.addEventListener("click", () => {
    const newName = prompt("Nom de la nouvelle catégorie :");
    if (newName && newName.trim() !== "") {
      apiPost({
        action: "edit",
        id,
        field: "new_category_create",
        value: newName,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      }).then((d) => {
        if (d.success) location.reload();
        else showFlash("Erreur création", "error");
      });
    }
  });
  menu.appendChild(newCatDiv);

  // Function to select a category
  const selectCategory = (catId, catName) => {
    apiPost({
      action: "edit",
      id,
      field: "id_categorie",
      value: catId,
      csrf_token: window.csrfToken,
      database_id: window.databaseId,
    }).then((d) => {
      if (d.success) location.reload();
      else showFlash("Erreur mise à jour", "error");
    });
  };

  // Position and show menu (robust: handle missing parent/target)
  let replaced = false;
  try {
    if (parent && parent.contains && parent.contains(target)) {
      parent.replaceChild(menu, target);
      replaced = true;
    } else {
      // Fallback: hide target and insert menu after it (or append to parent)
      try {
        target.style.display = "none";
      } catch (e) {}
      const insertParent = target.parentNode || parent || document.body;
      if (insertParent && insertParent.insertBefore) {
        insertParent.insertBefore(menu, target.nextSibling);
      } else {
        document.body.appendChild(menu);
      }
    }
  } catch (e) {
    // ultimate fallback
    document.body.appendChild(menu);
  }
  menu.focus();

  // Close menu on blur — restore DOM depending on how it was inserted
  const closeMenu = () => {
    setTimeout(() => {
      if (replaced) {
        try {
          if (parent && parent.contains(menu))
            parent.replaceChild(target, menu);
          try {
            delete target.dataset.catMenuOpen;
          } catch (e) {}
        } catch (e) {}
      } else {
        try {
          if (menu && menu.parentNode) menu.parentNode.removeChild(menu);
        } catch (e) {}
        try {
          target.style.display = "";
          try {
            delete target.dataset.catMenuOpen;
          } catch (e) {}
        } catch (e) {}
      }
    }, 150);
  };

  menu.addEventListener("blur", closeMenu);
  menu.tabIndex = 0;
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

function initConsultationListeners() {
  const searchInput = document.getElementById("searchInput");
  const categorySelect = document.getElementById("categoryFilter");
  if (searchInput) searchInput.addEventListener("input", filterItems);
  if (categorySelect) categorySelect.addEventListener("change", filterItems);

  const grid = document.getElementById("inventoryGrid");
  if (!grid) return;

  grid.addEventListener("click", function (e) {
    const el = e.target.closest("[data-action]");
    if (!el) return;
    const action = el.dataset.action;
    const id = el.dataset.id;
    e.preventDefault();

    if (action === "change-image") return changeImage(id);
    if (action === "edit-field")
      return editField(id, el.dataset.field, el.dataset.value);
    if (action === "edit-category")
      return editFieldderoul(id, el.dataset.field, el.dataset.value, {
        currentTarget: el,
      });
    if (action === "qty-inc") return updateQuantity(id, "inc");
    if (action === "qty-dec") return updateQuantity(id, "dec");
    if (action === "delete") return deleteObject(id);
  });
  // After wiring listeners, fetch data and render
  fetchAndRenderInventory();
}

document.addEventListener("DOMContentLoaded", initConsultationListeners);

/**
 * Initialise les sections de paramètres pour qu'elles soient repliables
 * - transforme chaque .settings-section en entête cliquable + body
 * - par défaut les sections sont fermées (classe .collapsed)
 */
function initSettingsCollapsible() {
  const sections = document.querySelectorAll(".settings-section");
  if (!sections || sections.length === 0) return;

  sections.forEach((section) => {
    // skip if already transformed
    if (section.dataset.collapsible === "1") return;

    const h2 = section.querySelector("h2");
    if (!h2) return;

    // create span container with arrow
    const span = document.createElement("div");
    span.className = "section-header";
    span.tabIndex = 0;

    const arrow = document.createElement("span");
    arrow.className = "section-arrow";
    arrow.textContent = "▸";
    arrow.classList.add("box");
    h2.classList.add("box");

    span.appendChild(h2); // moves the h2 into span
    span.appendChild(arrow);
    // create body and move all remaining nodes (including text nodes) into it
    const body = document.createElement("span");
    body.className = "section-body";

    // Insert header as first child
    section.insertBefore(span, section.firstChild);

    // Move remaining nodes (including text nodes) into body
    while (section.childNodes.length > 1) {
      body.appendChild(section.childNodes[1]);
    }

    section.appendChild(body);

    // start collapsed: hide body via inline style to ensure everything is hidden
    section.classList.add("collapsed");
    body.style.display = "none";
    section.dataset.collapsible = "1";
    span.setAttribute("role", "button");
    span.setAttribute("aria-expanded", "false");

    const toggle = () => {
      const nowCollapsed = section.classList.toggle("collapsed");
      if (nowCollapsed) {
        body.style.display = "none";
        arrow.textContent = "▸";
        span.setAttribute("aria-expanded", "false");
      } else {
        body.style.display = "";
        arrow.textContent = "▾";
        span.setAttribute("aria-expanded", "true");
      }
    };

    span.addEventListener("click", toggle);
    span.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        toggle();
      }
    });
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initSettingsCollapsible);
} else {
  initSettingsCollapsible();
}

function initGlobalListeners() {
  // data-action delegation
  document.addEventListener("click", function (e) {
    const el = e.target.closest("[data-action]");
    if (!el) return;
    const action = el.dataset.action;

    if (action === "toggle-create") {
      const form = document.getElementById("createForm");
      if (!form) return;
      console.log(form.style.display);
      form.style.display =
        form.style.display === "none" || form.style.display === ""
          ? "block"
          : "none";
      console.log(form.style.display);
      const name = document.getElementById("name");
      if (name) name.focus();
      return;
    }

    // Settings: update database info
    if (action === "update-database") {
      const name = document.getElementById("name")?.value || "";
      const description = document.getElementById("description")?.value || "";
      const redirectOnAdd = document.getElementById("redirect_on_add")?.checked
        ? 1
        : 0;
      const skipSourceModal = document.getElementById("skip_source_modal")
        ?.checked
        ? 1
        : 0;
      const preferGallery = document.getElementById("prefer_gallery")?.checked
        ? 1
        : 0;

      apiPost({
        action: "update",
        name,
        description,
        redirect_on_add: redirectOnAdd,
        skip_source_modal: skipSourceModal,
        prefer_gallery: preferGallery,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      })
        .then((d) => {
          if (d.success) {
            showFlash("Modifications enregistrées", "success");
            setTimeout(() => location.reload(), 1000);
          } else
            showFlash(
              "Erreur: " + (d.error || "Impossible de sauvegarder"),
              "error",
            );
        })
        .catch(() => showFlash("Erreur réseau", "error"));
      return;
    }

    // Settings: add user
    if (action === "add-user") {
      const form = document.getElementById("addUserForm");
      if (!form) return;
      const username = form.querySelector("#username")?.value || "";
      const permission = form.querySelector("#permission")?.value || "view";
      apiPost({
        action: "add_user",
        username,
        permission,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      })
        .then((d) => {
          if (d.success) {
            showFlash("Utilisateur ajouté", "success");
            setTimeout(() => location.reload(), 1000);
          } else
            showFlash(
              "Erreur: " + (d.error || "Impossible d'ajouter"),
              "error",
            );
        })
        .catch(() => showFlash("Erreur réseau", "error"));
      return;
    }

    // Settings: remove user
    if (action === "remove-user") {
      const permId = el.dataset.permissionId;
      if (!permId) return;
      apiPost({
        action: "remove_user",
        permission_id: permId,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      })
        .then((d) => {
          if (d.success) {
            const row = el.closest("tr");
            if (row) row.remove();
            else location.reload();
          } else showFlash("Erreur suppression", "error");
        })
        .catch(() => showFlash("Erreur réseau", "error"));
      return;
    }

    // Settings: rename category
    if (action === "rename-category") {
      const catId = el.dataset.categoryId;
      const input = document.getElementById("cat-name-" + catId);
      const newName = input ? input.value.trim() : "";
      if (!newName) {
        showFlash("Nom requis", "error");
        return;
      }
      apiPost({
        action: "rename_category",
        category_id: catId,
        new_name: newName,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      })
        .then((d) => {
          if (d.success) {
            showFlash("Renommé", "success");
            setTimeout(() => location.reload(), 1000);
          } else showFlash("Erreur renommage", "error");
        })
        .catch(() => showFlash("Erreur réseau", "error"));
      return;
    }

    // Settings: delete category
    if (action === "delete-category") {
      const catId = el.dataset.categoryId;
      if (!catId) return;
      apiPost({
        action: "delete_category",
        category_id: catId,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      })
        .then((d) => {
          if (d.success) {
            // remove element from DOM if present
            const parent = el.closest("li") || el.closest("div");
            if (parent) parent.remove();
            else location.reload();
          } else showFlash("Erreur suppression catégorie", "error");
        })
        .catch(() => showFlash("Erreur réseau", "error"));
      return;
    }

    // Settings: add root category
    if (action === "add-root-category") {
      const input = document.getElementById("new-root-cat-name");
      const name = input ? input.value.trim() : "";
      if (!name) {
        showFlash("Nom requis", "error");
        return;
      }
      apiPost({
        action: "create_category",
        name: name,
        parent_id: 0,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      })
        .then((d) => {
          if (d.success) {
            location.reload();
          } else showFlash("Erreur création: " + (d.error || ""), "error");
        })
        .catch(() => showFlash("Erreur réseau", "error"));
      return;
    }

    // Settings: add subcategory
    if (action === "add-subcategory") {
      const parentId = el.dataset.parentId;
      const input = document.getElementById("subcat-input-" + parentId);
      const name = input ? input.value.trim() : "";
      if (!name) {
        showFlash("Nom requis", "error");
        return;
      }
      apiPost({
        action: "add_subcategory",
        parent_id: parentId,
        name,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      })
        .then((d) => {
          if (d.success) {
            showFlash("Sous-catégorie ajoutée", "success");
            setTimeout(() => location.reload(), 1000);
          } else showFlash("Erreur création", "error");
        })
        .catch(() => showFlash("Erreur réseau", "error"));
      return;
    }

    // Settings: delete database (admin)
    if (action === "delete-database") {
      const checkbox = document.getElementById("confirmCheck");
      if (!checkbox || checkbox.checked) {
        apiPost({
          action: "delete_database",
          confirm: "yes",
          csrf_token: window.csrfToken,
          database_id: window.databaseId,
        })
          .then((d) => {
            if (d.success) {
              showFlash("Base supprimée", "success");
              setTimeout(() => (window.location = "index"), 1000);
            } else showFlash("Erreur suppression", "error");
          })
          .catch(() => showFlash("Erreur réseau", "error"));
      } else {
        showFlash("Veuillez cocher la case pour confirmer", "error");
      }
      return;
    }

    if (action === "submit-delete") {
      const checkbox = document.getElementById("confirmCheck");
      if (!checkbox || checkbox.checked) {
        const form = document.getElementById("deleteForm");
        if (!form) return;
        const input = form.querySelector('input[name="confirm"]');
        if (input) input.value = "yes";
        form.submit();
      } else {
        showFlash("Veuillez cocher la case pour confirmer", "error");
      }
      return;
    }

    if (action === "toggle-add") {
      const form = document.getElementById("addForm");
      if (!form) return;
      form.style.display =
        form.style.display === "none" || form.style.display === ""
          ? "block"
          : "none";
      const firstInput = form.querySelector("input, textarea, select");
      if (firstInput) firstInput.focus();
      return;
    }

    if (action === "flash-close") {
      const flash = el.closest(".flash-message");
      if (flash) {
        flash.classList.add("fade-out");
        setTimeout(() => (flash.style.display = "none"), 300);
      }
      return;
    }
  });

  // data-confirm interception
  document.addEventListener(
    "click",
    function (e) {
      const conf = e.target.closest("[data-confirm]");
      if (!conf) return;
      const msg = conf.dataset.confirm || "Confirmer ?";
      if (!confirm(msg)) {
        e.preventDefault();
        e.stopPropagation();
      }
    },
    true,
  );

  // Listener pour le changement de permission (select)
  document.addEventListener("change", function (e) {
    const el = e.target.closest("[data-action='update-permission']");
    if (!el) return;

    const userId = el.dataset.userId;
    const newPerm = el.value;

    apiPost({
      action: "update_permission",
      user_id: userId,
      new_permission: newPerm,
      csrf_token: window.csrfToken,
      database_id: window.databaseId,
    })
      .then((d) => {
        if (!d.success) {
          showFlash(
            "Erreur: " + (d.error || "Impossible de mettre à jour"),
            "error",
          );
          setTimeout(() => location.reload(), 1000);
        } else {
          // Met à jour la couleur du badge immédiatement
          el.className = "badge badge-" + newPerm + " permission-toggle";
        }
      })
      .catch(() => {
        showFlash("Erreur réseau", "error");
        setTimeout(() => location.reload(), 1000);
      });
  });
}

/**
 * Gestion du Thème (Clair / Sombre)
 */
function initTheme() {
  const toggle = document.getElementById("theme-toggle");
  const stored = localStorage.getItem("theme");
  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  const isDark = stored === "dark" || (!stored && prefersDark);

  // Appliquer le thème au chargement
  document.documentElement.setAttribute(
    "data-theme",
    isDark ? "dark" : "light",
  );

  if (toggle) {
    // Si c'est un switch (checkbox)
    if (toggle.type === "checkbox") {
      toggle.checked = isDark;
      toggle.addEventListener("change", () => {
        const next = toggle.checked ? "dark" : "light";
        document.documentElement.setAttribute("data-theme", next);
        localStorage.setItem("theme", next);
      });
    } else {
      // Ancien comportement (bouton)
      toggle.textContent = isDark ? "☀️" : "🌙";
      toggle.addEventListener("click", () => {
        const current = document.documentElement.getAttribute("data-theme");
        const next = current === "light" ? "dark" : "light";
        document.documentElement.setAttribute("data-theme", next);
        localStorage.setItem("theme", next);
        toggle.textContent = next === "light" ? "🌙" : "☀️";
      });
    }
  }
}

/**
 * Charge et affiche les statistiques
 */
async function initStats() {
  const container = document.getElementById("globalStats");
  if (!container) return;

  try {
    const res = await fetch("api/stats.php");
    const d = await res.json();

    if (d.success && d.total_items > 0) {
      container.style.display = "block";

      // Valeur totale
      document.getElementById("statTotalValue").textContent =
        new Intl.NumberFormat("fr-FR", {
          style: "currency",
          currency: "EUR",
        }).format(d.total_value);
      document.getElementById("statTotalItems").textContent =
        d.total_items + " objets";

      // Chart Catégories
      const ctxCat = document.getElementById("chartCategories");
      if (ctxCat) {
        new Chart(ctxCat, {
          type: "doughnut",
          data: {
            labels: d.categories.map((c) => c.cat_name),
            datasets: [
              {
                data: d.categories.map((c) => c.count),
                backgroundColor: [
                  "#6366f1",
                  "#10b981",
                  "#f59e0b",
                  "#ef4444",
                  "#8b5cf6",
                  "#ec4899",
                  "#14b8a6",
                ],
                borderWidth: 0,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: "right",
                labels: {
                  color: getComputedStyle(document.body).getPropertyValue(
                    "--text-muted",
                  ),
                },
              },
              title: {
                display: true,
                text: "Répartition par catégorie",
                color: getComputedStyle(document.body).getPropertyValue(
                  "--text-main",
                ),
              },
            },
          },
        });
      }

      // Chart Statut
      const ctxStat = document.getElementById("chartStatus");
      if (ctxStat) {
        new Chart(ctxStat, {
          type: "pie",
          data: {
            labels: ["Disponible", "En utilisation", "Dégradé/HS"],
            datasets: [
              {
                data: [d.status.available, d.status.used, d.status.degraded],
                backgroundColor: ["#10b981", "#3b82f6", "#ef4444"],
                borderWidth: 0,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: "right",
                labels: {
                  color: getComputedStyle(document.body).getPropertyValue(
                    "--text-muted",
                  ),
                },
              },
              title: {
                display: true,
                text: "État du stock",
                color: getComputedStyle(document.body).getPropertyValue(
                  "--text-main",
                ),
              },
            },
          },
        });
      }
    }
  } catch (e) {
    console.error("Erreur stats", e);
  }
}

document.addEventListener("DOMContentLoaded", initGlobalListeners);
document.addEventListener("DOMContentLoaded", initTheme);
document.addEventListener("DOMContentLoaded", initStats);

function initAddFormListeners() {
  const dropZone = document.getElementById("dropZone");
  const fileInput = document.getElementById("fileInput");
  const previewImage = document.getElementById("previewImage");
  const placeholder = document.getElementById("placeholder");
  const categorySelect = document.getElementById("add_item_category_id");

  if (categorySelect) {
    categorySelect.addEventListener("change", async function () {
      if (this.value === "NEW") {
        const newName = prompt("Nom de la nouvelle catégorie :");
        if (newName && newName.trim() !== "") {
          this.value = "NEW:" + newName;
        } else {
          this.value = "0";
        }
      } else if (this.value && this.value.indexOf("NEW_SUB:") === 0) {
        const parts = this.value.split(":");
        const parentId = parseInt(parts[1], 10) || 0;
        const newName = prompt(
          "Nom de la nouvelle sous-catégorie pour ce parent :",
        );
        if (newName && newName.trim() !== "") {
          try {
            const d = await apiPost({
              action: "create_category",
              name: newName,
              parent_id: parentId,
              csrf_token: window.csrfToken,
              database_id: window.databaseId,
            });
            if (d && d.success && d.id) {
              this.value = d.id;
            } else {
              alert("Erreur création: " + (d.error || ""));
              this.value = "0";
            }
          } catch (err) {
            alert("Erreur réseau");
            this.value = "0";
          }
        } else {
          this.value = "0";
        }
      }
    });
  }

  if (!dropZone || !fileInput) return;

  // Empêcher la propagation du clic pour éviter que le clic sur l'input (enfant)
  // ne remonte à la dropZone (parent) et ne rouvre la modale immédiatement.
  fileInput.addEventListener("click", (e) => e.stopPropagation());

  dropZone.addEventListener("click", () => {
    openSourceChoice((source) => {
      if (source === "camera") fileInput.setAttribute("capture", "environment");
      else fileInput.removeAttribute("capture");
      fileInput.click();
    });
  });

  ["dragover", "dragleave", "drop"].forEach((eventName) => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
    });
  });

  dropZone.addEventListener("dragover", () => {
    dropZone.classList.add("drag-active");
    dropZone.style.background = "#ecf0f1";
  });

  dropZone.addEventListener("dragleave", () => {
    dropZone.style.background = "#f8f9fa";
  });

  dropZone.addEventListener("drop", (e) => {
    dropZone.style.background = "#f8f9fa";
    const files = e.dataTransfer.files;
    if (files.length > 0) {
      fileInput.files = files;
      handlePreview(files[0]);
    }
  });

  fileInput.addEventListener("change", (e) => {
    if (e.target.files.length > 0) handlePreview(e.target.files[0]);
  });

  function handlePreview(file) {
    if (!file || !file.type.startsWith("image/")) return;
    if (file.size > 5 * 1024 * 1024) {
      showFlash("L'image est trop volumineuse (max 5MB)", "error");
      fileInput.value = "";
      return;
    }
    const allowed = ["image/jpeg", "image/png", "image/webp", "image/gif"];
    if (!allowed.includes(file.type)) {
      showFlash(
        "Format d'image non autorisé. Utilisez JPG, PNG, WEBP ou GIF",
        "error",
      );
      fileInput.value = "";
      return;
    }
    const reader = new FileReader();
    reader.onload = function (ev) {
      if (previewImage) {
        previewImage.src = ev.target.result;
        previewImage.style.display = "block";
      }
      if (placeholder) placeholder.style.display = "none";
      dropZone.style.border = "2px dashed #3498db";
    };
    reader.readAsDataURL(file);
  }

  // Intercept form submit to POST to API
  const addForm = document.querySelector(".add-form-full");
  if (addForm) {
    addForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const fd = new FormData(addForm);
      fd.append("action", "create");
      fd.append("csrf_token", window.csrfToken);
      fd.append("database_id", window.databaseId);
      try {
        const res = await fetch("api/database.php", {
          method: "POST",
          body: fd,
        });

        // Lecture robuste de la réponse pour voir l'erreur PHP si crash
        const text = await res.text();
        let d;
        try {
          d = JSON.parse(text);
        } catch (e) {
          throw new Error("Erreur serveur : " + text.substring(0, 200));
        }

        if (d.success) {
          if (window.dbRedirectOnAdd) {
            window.location.href = "database/" + window.databaseId;
            return;
          }
          // Append new object to grid
          const grid = document.getElementById("inventoryGrid");
          if (grid) {
            renderInventory([d.object], grid);
          }
          // reset form and close
          addForm.reset();
          const formWrapper = document.getElementById("addForm");
          if (formWrapper) formWrapper.style.display = "none";
          showFlash("Objet ajouté", "success");
        } else {
          showFlash("Erreur: " + (d.error || "Impossible d'ajouter"), "error");
        }
      } catch (err) {
        console.error(err);
        showFlash(err.message || "Erreur réseau", "error");
      }
    });
  }
}

function initProfileListeners() {
  const profileForm = document.getElementById("profileForm");
  const profileInput = document.getElementById("profileImageInput");
  const profilePreview = document.getElementById("profileAvatarPreview");

  if (profileInput && profilePreview) {
    profileInput.addEventListener("change", function (e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (ev) {
          if (profilePreview.tagName === "IMG") {
            profilePreview.src = ev.target.result;
          } else {
            // Si c'était une div (pas d'image avant), on la remplace par une img
            const img = document.createElement("img");
            img.src = ev.target.result;
            img.className = "profile-avatar";
            img.id = "profileAvatarPreview";
            profilePreview.parentNode.replaceChild(img, profilePreview);
          }
        };
        reader.readAsDataURL(file);
      }
    });
  }

  if (profileForm) {
    profileForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const fd = new FormData(profileForm);
      fd.append("action", "update_profile");
      fd.append("csrf_token", window.csrfToken);

      try {
        const res = await fetch("api/user.php", { method: "POST", body: fd });

        // On récupère le texte d'abord pour pouvoir l'afficher si ce n'est pas du JSON
        const text = await res.text();
        let d;
        try {
          d = JSON.parse(text);
        } catch (e) {
          throw new Error("Erreur serveur : " + text.substring(0, 150)); // Affiche le début de l'erreur PHP
        }

        if (d.success) {
          showFlash("Profil mis à jour !", "success");
          setTimeout(() => location.reload(), 1000);
        } else {
          showFlash(
            "Erreur : " + (d.message || "Impossible de mettre à jour"),
            "error",
          );
        }
      } catch (err) {
        console.error(err);
        showFlash(err.message || "Erreur réseau", "error");
      }
    });
  }
}

document.addEventListener("DOMContentLoaded", initAddFormListeners);
document.addEventListener("DOMContentLoaded", initProfileListeners);

/**
 * Trie les objets selon le critère choisi
 */
function sortObjects(objects, sortType) {
  const sorted = [...objects];
  switch (sortType) {
    case "date_desc": // Plus récent (ID desc)
      return sorted.sort((a, b) => b.id - a.id);
    case "date_asc": // Plus ancien (ID asc)
      return sorted.sort((a, b) => a.id - b.id);
    case "alpha_asc": // A-Z
      return sorted.sort((a, b) => a.nom.localeCompare(b.nom));
    case "alpha_desc": // Z-A
      return sorted.sort((a, b) => b.nom.localeCompare(a.nom));
    case "qty_desc": // Quantité High-Low
      return sorted.sort((a, b) => b.quantite - a.quantite);
    case "qty_asc": // Quantité Low-High
      return sorted.sort((a, b) => a.quantite - b.quantite);
    default:
      return sorted;
  }
}

/**
 * Injecte le menu de tri dans la zone de recherche
 */
function setupSortDropdown() {
  const searchZone = document.querySelector(".search-zone");
  if (!searchZone || document.getElementById("sortOrder")) return;

  const select = document.createElement("select");
  select.id = "sortOrder";

  const options = [
    { val: "date_desc", text: "📅 Plus récent" },
    { val: "date_asc", text: "📅 Plus ancien" },
    { val: "alpha_asc", text: "🔤 Nom (A - Z)" },
    { val: "alpha_desc", text: "🔤 Nom (Z - A)" },
    { val: "qty_desc", text: "🔢 Quantité ( - )" },
    { val: "qty_asc", text: "🔢 Quantité ( + )" },
  ];

  options.forEach((opt) => {
    const o = document.createElement("option");
    o.value = opt.val;
    o.textContent = opt.text;
    select.appendChild(o);
  });

  select.addEventListener("change", () => {
    // Tri côté serveur maintenant
    window.currentPage = 1;
    fetchAndRenderInventory();
  });

  searchZone.appendChild(select);
}

/**
 * Injecte le sélecteur de nombre d'éléments par page
 */
function setupLimitSelector() {
  const searchZone = document.querySelector(".search-zone");
  if (!searchZone || document.getElementById("limitSelector")) return;

  const select = document.createElement("select");
  select.id = "limitSelector";
  select.title = "Éléments par page";

  [20, 50, 100, 200].forEach((val) => {
    const o = document.createElement("option");
    o.value = val;
    o.textContent = val + " / page";
    if (val === window.itemsPerPage) o.selected = true;
    select.appendChild(o);
  });

  select.addEventListener("change", () => {
    window.itemsPerPage = parseInt(select.value);
    window.currentPage = 1; // Retour page 1
    fetchAndRenderInventory();
  });

  // Insérer avant le tri si possible, sinon à la fin
  const sort = document.getElementById("sortOrder");
  if (sort) searchZone.insertBefore(select, sort);
  else searchZone.appendChild(select);
}

async function fetchAndRenderInventory() {
  const grid = document.getElementById("inventoryGrid");
  const catSelect = document.getElementById("categoryFilter");
  const searchInput = document.getElementById("searchInput");
  const sortSelect = document.getElementById("sortOrder");

  if (!grid) return;

  // Récupération des valeurs de filtres
  const search = searchInput ? searchInput.value.trim() : "";
  const category = catSelect ? catSelect.value : "";
  const sort = sortSelect ? sortSelect.value : "date_desc";

  try {
    // Construction de l'URL avec pagination et filtres
    const params = new URLSearchParams({
      action: "list",
      database_id: window.databaseId,
      page: window.currentPage,
      limit: window.itemsPerPage,
      search: search,
      category: category,
      sort: sort,
    });

    const res = await fetch("api/database.php?" + params.toString());
    const data = await res.json();
    if (!data.success) {
      grid.innerHTML = '<div class="error">Erreur chargement</div>';
      return;
    }
    const objects = data.objects || [];
    const totalItems = data.total || objects.length; // L'API renvoie le total

    const categories = data.categories || [];
    renderCategories(categories, catSelect);
    setupSortDropdown(); // Initialisation du menu de tri
    setupLimitSelector(); // Initialisation du sélecteur de limite
    renderInventory(objects, grid);
    renderPagination(totalItems); // Affichage des contrôles de page
  } catch (e) {
    grid.innerHTML = '<div class="error">Erreur réseau</div>';
  }
}

function renderCategories(categories, selectEl) {
  if (!selectEl) return;
  // Build tree
  const map = {};
  categories.forEach((c) => {
    map[c.id] = Object.assign({}, c, { subs: [] });
  });
  const roots = [];
  categories.forEach((c) => {
    if (c.parent_id == null) roots.push(map[c.id]);
    else if (map[c.parent_id]) map[c.parent_id].subs.push(map[c.id]);
  });
  // expose globalCategories for other functions
  window.globalCategories = roots;

  // Populate hidden select for compatibility
  selectEl.innerHTML = '<option value="">Toutes les catégories</option>';
  roots.forEach((p) => {
    const o = document.createElement("option");
    o.value = p.nom;
    o.textContent = p.nom;
    selectEl.appendChild(o);
    if (p.subs)
      p.subs.forEach((s) => {
        const so = document.createElement("option");
        so.value = s.nom;
        so.textContent = "↳ " + s.nom;
        selectEl.appendChild(so);
      });
  });

  // Setup custom dropdown
  selectEl.style.display = "none";
  let trigger = document.getElementById("categoryFilterTrigger");
  if (!trigger) {
    trigger = document.createElement("div");
    trigger.id = "categoryFilterTrigger";
    trigger.className = "form-input";
    trigger.style.cursor = "pointer";
    trigger.style.display = "flex";
    trigger.style.alignItems = "center";
    trigger.style.justifyContent = "space-between";
    trigger.style.minWidth = "250px";
    trigger.innerHTML =
      '<span id="catFilterLabel">Toutes les catégories</span> <span style="font-size: 0.8em">▼</span>';

    if (selectEl.parentNode) {
      selectEl.parentNode.insertBefore(trigger, selectEl.nextSibling);
    }

    trigger.addEventListener("click", (e) => {
      e.stopPropagation();
      openFilterMenu(trigger, selectEl);
    });
  }
}

function openFilterMenu(trigger, selectEl) {
  const existing = document.querySelector(".category-tree-menu.filter-menu");
  if (existing) {
    existing.remove();
    return;
  }

  const menu = document.createElement("div");
  menu.className = "category-tree-menu filter-menu";

  const rect = trigger.getBoundingClientRect();
  menu.style.position = "absolute";
  menu.style.top = window.scrollY + rect.bottom + 5 + "px";
  menu.style.left = window.scrollX + rect.left + "px";
  menu.style.width = rect.width + "px";
  menu.style.zIndex = 2000;
  menu.style.maxHeight = "300px";

  // Option: Toutes les catégories
  const optAll = document.createElement("div");
  optAll.className = "cat-option";
  optAll.textContent = "Toutes les catégories";
  optAll.addEventListener("click", () => {
    selectEl.value = "";
    document.getElementById("catFilterLabel").textContent =
      "Toutes les catégories";
    filterItems();
    menu.remove();
  });
  menu.appendChild(optAll);

  if (window.globalCategories) {
    window.globalCategories.forEach((p) => {
      const parentContainer = document.createElement("div");
      parentContainer.style.cssText = `border-bottom: 1px solid var(--border);`;

      const parentHeader = document.createElement("div");
      parentHeader.className = "cat-parent";

      const arrow = document.createElement("span");
      arrow.className = "cat-arrow";
      arrow.textContent = "▼";

      const label = document.createElement("span");
      label.textContent = p.nom;
      label.style.flex = "1";

      parentHeader.appendChild(arrow);
      parentHeader.appendChild(label);
      parentContainer.appendChild(parentHeader);

      const childrenContainer = document.createElement("div");
      childrenContainer.className = "cat-children";

      let isExpanded = false;
      const toggleChildren = (e) => {
        e.stopPropagation();
        isExpanded = !isExpanded;
        childrenContainer.style.display = isExpanded ? "block" : "none";
        arrow.style.transform = isExpanded ? "rotate(180deg)" : "rotate(0deg)";
      };
      parentHeader.addEventListener("click", toggleChildren);

      // Select Parent
      const selectParent = document.createElement("div");
      selectParent.textContent = "Sélectionner: " + p.nom;
      selectParent.style.padding = "10px 12px 10px 30px";
      selectParent.style.cursor = "pointer";
      selectParent.style.borderBottom = "1px solid var(--border)";
      selectParent.style.color = "var(--text-main)";
      selectParent.style.fontSize = "13px";

      selectParent.addEventListener("click", () => {
        selectEl.value = p.nom;
        document.getElementById("catFilterLabel").textContent = p.nom;
        filterItems();
        menu.remove();
      });
      selectParent.addEventListener(
        "mouseover",
        () => (selectParent.style.backgroundColor = "rgba(255,255,255,0.05)"),
      );
      selectParent.addEventListener(
        "mouseout",
        () => (selectParent.style.backgroundColor = "transparent"),
      );

      childrenContainer.appendChild(selectParent);

      // Subcategories
      if (p.subs && p.subs.length > 0) {
        p.subs.forEach((s) => {
          const subOption = document.createElement("div");
          subOption.textContent = s.nom;
          subOption.style.padding = "9px 12px 9px 40px";
          subOption.style.cursor = "pointer";
          subOption.style.borderBottom = "1px solid var(--border)";
          subOption.style.color = "var(--text-muted)";
          subOption.style.fontSize = "13px";

          subOption.addEventListener("click", () => {
            selectEl.value = s.nom;
            document.getElementById("catFilterLabel").textContent = s.nom;
            filterItems();
            menu.remove();
          });
          subOption.addEventListener("mouseover", () => {
            subOption.style.backgroundColor = "rgba(255,255,255,0.05)";
            subOption.style.color = "var(--text-main)";
          });
          subOption.addEventListener("mouseout", () => {
            subOption.style.backgroundColor = "transparent";
            subOption.style.color = "var(--text-muted)";
          });

          childrenContainer.appendChild(subOption);
        });
      }
      parentContainer.appendChild(childrenContainer);
      menu.appendChild(parentContainer);
    });
  }

  document.body.appendChild(menu);

  const closeHandler = (e) => {
    if (!menu.contains(e.target) && !trigger.contains(e.target)) {
      menu.remove();
      document.removeEventListener("click", closeHandler);
    }
  };
  setTimeout(() => document.addEventListener("click", closeHandler), 0);
}

/**
 * Génère les contrôles de pagination
 */
function renderPagination(totalItems) {
  let container = document.getElementById("paginationContainer");
  const grid = document.getElementById("inventoryGrid");

  // Créer le conteneur s'il n'existe pas
  if (!container) {
    container = document.createElement("div");
    container.id = "paginationContainer";
    container.className = "pagination-controls";
    if (grid && grid.parentNode) {
      grid.parentNode.insertBefore(container, grid.nextSibling);
    }
  }

  container.innerHTML = "";
  const totalPages = Math.ceil(totalItems / window.itemsPerPage);

  if (totalPages <= 1) {
    container.style.display = "none";
    return;
  }
  container.style.display = "flex";

  // Bouton Précédent
  const prevBtn = document.createElement("button");
  prevBtn.className = "pagination-btn";
  prevBtn.textContent = "←";
  prevBtn.disabled = window.currentPage <= 1;
  prevBtn.onclick = () => {
    if (window.currentPage > 1) {
      window.currentPage--;
      fetchAndRenderInventory();
    }
  };

  // Info Page
  const info = document.createElement("span");
  info.className = "pagination-info";
  info.textContent = `Page ${window.currentPage} / ${totalPages}`;

  // Bouton Suivant
  const nextBtn = document.createElement("button");
  nextBtn.className = "pagination-btn";
  nextBtn.textContent = "→";
  nextBtn.disabled = window.currentPage >= totalPages;
  nextBtn.onclick = () => {
    if (window.currentPage < totalPages) {
      window.currentPage++;
      fetchAndRenderInventory();
    }
  };

  container.appendChild(prevBtn);
  container.appendChild(info);
  container.appendChild(nextBtn);
}

function renderInventory(objects, gridEl) {
  gridEl.innerHTML = "";
  if (!objects.length) {
    gridEl.innerHTML =
      '<div class="empty-state" onclick="window.location.href=\'database/add/' +
      window.databaseId +
      '\'" style="cursor: pointer;">Aucun objet dans cette base.<br><strong>Cliquez ici pour en ajouter un.</strong></div>';
    return;
  }
  objects.forEach((row) => {
    const hasImg = !!row.image_path;
    const card = document.createElement("div");
    card.className = "card";
    card.dataset.name = (row.nom || "").toLowerCase();
    card.dataset.cat = row.nom_categorie || "";
    card.dataset.parent = row.parent_nom || "";

    let imgContainer;
    if (hasImg) {
      imgContainer = document.createElement("div");
      imgContainer.className = "card-image-wrapper";
      imgContainer.setAttribute("data-action", "change-image");
      imgContainer.setAttribute("data-id", row.id);

      const img = document.createElement("img");
      img.src = "uploads/" + row.image_path;
      img.alt = "Photo objet";

      const eye = document.createElement("div");
      eye.className = "view-image-icon";
      eye.innerHTML = "🔍";
      eye.title = "Voir en grand";
      eye.onclick = (e) => {
        e.stopPropagation();
        viewFullImage("uploads/" + row.image_path);
      };

      imgContainer.appendChild(img);
      imgContainer.appendChild(eye);
    } else {
      imgContainer = document.createElement("div");
      imgContainer.className = "card-no-image";
      imgContainer.setAttribute("data-action", "change-image");
      imgContainer.setAttribute("data-id", row.id);
      imgContainer.style.cursor = "pointer";
      imgContainer.innerHTML = "<span>📷</span><p>Ajouter photo</p>";
    }

    const details = document.createElement("div");
    details.className = "card-details";
    const h3 = document.createElement("h3");
    h3.style.cursor = "pointer";
    h3.style.borderBottom = "1px dashed rgba(0,0,0,0.1)";
    h3.setAttribute("data-action", "edit-field");
    h3.setAttribute("data-id", row.id);
    h3.setAttribute("data-field", "nom");
    h3.setAttribute("data-value", row.nom);
    h3.title = "Renommer l'objet";
    h3.textContent = row.nom;
    details.appendChild(h3);
    // Icône Info (Détails) - En haut à gauche
    const infoIcon = document.createElement("div");
    infoIcon.className = "info-icon";
    infoIcon.innerHTML = "🛈";
    infoIcon.title = "Détails complets";
    infoIcon.onclick = (e) => {
      e.stopPropagation();
      openObjectDetails(row);
    };
    details.appendChild(infoIcon);

    const tag = document.createElement("span");
    tag.className = "tag";
    tag.style.cursor = "pointer";
    tag.setAttribute("data-action", "edit-category");
    tag.setAttribute("data-id", row.id);
    tag.setAttribute("data-field", "id_categorie");
    tag.setAttribute("data-value", row.nom_categorie || "");
    tag.textContent =
      (row.parent_nom ? row.parent_nom + " -> " : "") +
      (row.nom_categorie || "Sans catégorie");
    details.appendChild(tag);

    const qtyZone = document.createElement("div");
    qtyZone.className = "qty-zone";
    const btnDec = document.createElement("button");
    btnDec.className = "btn-qty";
    btnDec.textContent = "-";
    btnDec.setAttribute("data-action", "qty-dec");
    btnDec.setAttribute("data-id", row.id);
    const qtySpan = document.createElement("span");
    qtySpan.className = "qty-val";
    qtySpan.id = "qty-" + row.id;
    qtySpan.setAttribute("data-action", "edit-field");
    qtySpan.setAttribute("data-id", row.id);
    qtySpan.setAttribute("data-field", "quantite");
    qtySpan.setAttribute("data-value", row.quantite);
    qtySpan.style.cursor = "pointer";
    qtySpan.style.borderBottom = "2px dashed #3498db";
    qtySpan.title = "Modifier la quantité";
    qtySpan.textContent = row.quantite;
    const btnInc = document.createElement("button");
    btnInc.className = "btn-qty";
    btnInc.textContent = "+";
    btnInc.setAttribute("data-action", "qty-inc");
    btnInc.setAttribute("data-id", row.id);
    qtyZone.appendChild(btnDec);
    qtyZone.appendChild(qtySpan);
    qtyZone.appendChild(btnInc);
    details.appendChild(qtyZone);

    if (window.userPermission === "admin") {
      const del = document.createElement("a");
      del.href = "#";
      del.className = "delete-link";
      del.setAttribute("data-action", "delete");
      del.setAttribute("data-id", row.id);
      del.textContent = "🗑 Supprimer";
      details.appendChild(del);
    }

    card.appendChild(imgContainer);
    card.appendChild(details);
    gridEl.appendChild(card);
  });
}

/* Dashboard: fetch and render databases if present */
async function fetchAndRenderDatabases() {
  const grid = document.getElementById("databasesGrid");
  if (!grid) return;
  try {
    const res = await fetch("api/dashboard.php");
    const d = await res.json();
    if (!d.success) {
      grid.innerHTML = '<div class="error">Erreur chargement</div>';
      return;
    }
    const list = d.databases || [];
    grid.innerHTML = "";
    if (!list.length) {
      grid.innerHTML =
        '<div class="no-databases"><p>Vous n\'avez aucune base de données pour le moment.</p><p>Créez-en une pour commencer à ajouter vos objets!</p></div>';
      return;
    }
    list.forEach((db) => {
      const card = document.createElement("div");
      card.className = "database-card";
      const header = document.createElement("div");
      header.className = "db-card-header";
      const h3 = document.createElement("h3");
      h3.textContent = db.name;
      header.appendChild(h3);
      if (db.owner_id == (window.userId || "")) {
        const s = document.createElement("span");
        s.className = "badge-owner";
        s.textContent = "Propriétaire";
        header.appendChild(s);
      } else if (db.permission) {
        const s = document.createElement("span");
        s.className = "badge-permission";
        const labels = { admin: "Admin", edit: "Modif.", view: "Lecture" };
        s.textContent = labels[db.permission] || db.permission;
        header.appendChild(s);
      }
      card.appendChild(header);
      if (db.description) {
        const p = document.createElement("p");
        p.className = "db-description";
        p.textContent = db.description;
        card.appendChild(p);
      }
      const footer = document.createElement("div");
      footer.className = "db-card-footer";
      const a = document.createElement("a");
      a.href = "database/" + db.id;
      a.className = "btn-link";
      a.textContent = "Consulter";
      footer.appendChild(a);
      const btnExport = document.createElement("a");
      btnExport.href = "export.php?id=" + db.id;
      btnExport.className = "btn-link";
      btnExport.textContent = "Exporter";
      btnExport.target = "_blank";
      footer.appendChild(btnExport);
      if (db.owner_id == (window.userId || "")) {
        const s2 = document.createElement("a");
        s2.href = "database/settings/" + db.id;
        s2.className = "btn-link";
        s2.textContent = "Paramètres";
        footer.appendChild(s2);
      }
      card.appendChild(footer);
      grid.appendChild(card);
    });
  } catch (e) {
    grid.innerHTML = '<div class="error">Erreur réseau</div>';
  }
}

// Intercept dashboard create form
document.addEventListener("DOMContentLoaded", function () {
  const createForm = document.getElementById("createDbForm");
  if (!createForm) return;
  createForm.addEventListener("submit", async function (e) {
    e.preventDefault();
    const fd = new FormData(createForm);
    fd.append("action", "create");
    try {
      const res = await fetch("api/dashboard.php", {
        method: "POST",
        body: fd,
      });
      const d = await res.json();
      if (d.success) {
        // refresh list
        fetchAndRenderDatabases();
        createForm.reset();
        const formWrapper = document.getElementById("createForm");
        if (formWrapper) formWrapper.style.display = "none";
        showFlash("Base créée", "success");
      } else {
        showFlash("Erreur: " + (d.error || "Impossible de créer"), "error");
      }
    } catch (err) {
      showFlash("Erreur réseau", "error");
    }
  });
  // fetch initial list
  fetchAndRenderDatabases();
});
