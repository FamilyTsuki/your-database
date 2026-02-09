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
      else alert("Erreur: " + (d.message || "Impossible de mettre à jour"));
    })
    .catch(() => alert("Erreur réseau"));
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
  const searchInput = document.getElementById("searchInput");
  const categorySelect = document.getElementById("categoryFilter");

  if (!searchInput || !categorySelect) return;

  const search = searchInput.value.toLowerCase().trim();
  const categoryFilter = categorySelect.value.toLowerCase().trim();

  document.querySelectorAll(".card").forEach((card) => {
    const name = (card.dataset.name || "").toLowerCase();
    const cat = (card.dataset.cat || "").toLowerCase();
    const parent = (card.dataset.parent || "").toLowerCase();
    const matchesSearch = name.includes(search);

    // On vérifie si le filtre est vide,
    // OU s'il correspond à la catégorie,
    // OU s'il correspond au parent
    const matchesCategory =
      categoryFilter === "" ||
      cat === categoryFilter ||
      parent === categoryFilter;

    if (matchesSearch && matchesCategory) {
      card.style.display = "flex";
    } else {
      card.style.display = "none";
    }
  });
}

/**
 * Ouvre le sélecteur d'image pour une fiche
 */
function changeImage(id) {
  const input = document.createElement("input");
  input.type = "file";
  input.accept = "image/*";
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
    else alert("Erreur: " + (d.error || "upload"));
  };
  input.click();
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
          alert("Erreur: " + (text || res.statusText));
        }
      } catch (err) {
        alert("Erreur réseau");
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
  } catch (e) {
    alert("Erreur lors de la mise à jour");
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
      else alert("Erreur suppression");
    })
    .catch(() => alert("Erreur réseau"));
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
            else alert("Erreur création sous-catégorie");
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
        else alert("Erreur création");
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
      else alert("Erreur mise à jour");
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
      apiPost({
        action: "update",
        name,
        description,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      })
        .then((d) => {
          if (d.success) {
            alert("Modifications enregistrées");
            location.reload();
          } else alert("Erreur: " + (d.error || "Impossible de sauvegarder"));
        })
        .catch(() => alert("Erreur réseau"));
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
            alert("Utilisateur ajouté");
            location.reload();
          } else alert("Erreur: " + (d.error || "Impossible d'ajouter"));
        })
        .catch(() => alert("Erreur réseau"));
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
          } else alert("Erreur suppression");
        })
        .catch(() => alert("Erreur réseau"));
      return;
    }

    // Settings: rename category
    if (action === "rename-category") {
      const catId = el.dataset.categoryId;
      const input = document.getElementById("cat-name-" + catId);
      const newName = input ? input.value.trim() : "";
      if (!newName) {
        alert("Nom requis");
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
            alert("Renommé");
            location.reload();
          } else alert("Erreur renommage");
        })
        .catch(() => alert("Erreur réseau"));
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
          } else alert("Erreur suppression catégorie");
        })
        .catch(() => alert("Erreur réseau"));
      return;
    }

    // Settings: add subcategory
    if (action === "add-subcategory") {
      const parentId = el.dataset.parentId;
      const input = document.getElementById("subcat-input-" + parentId);
      const name = input ? input.value.trim() : "";
      if (!name) {
        alert("Nom requis");
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
            alert("Sous-catégorie ajoutée");
            location.reload();
          } else alert("Erreur création");
        })
        .catch(() => alert("Erreur réseau"));
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
              alert("Base supprimée");
              window.location = "index.php";
            } else alert("Erreur suppression");
          })
          .catch(() => alert("Erreur réseau"));
      } else {
        alert("Veuillez cocher la case pour confirmer");
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
        alert("Veuillez cocher la case pour confirmer");
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
}

document.addEventListener("DOMContentLoaded", initGlobalListeners);

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

  dropZone.addEventListener("click", () => fileInput.click());

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
      alert("L'image est trop volumineuse (max 5MB)");
      fileInput.value = "";
      return;
    }
    const allowed = ["image/jpeg", "image/png", "image/webp", "image/gif"];
    if (!allowed.includes(file.type)) {
      alert("Format d'image non autorisé. Utilisez JPG, PNG, WEBP ou GIF");
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
        const d = await res.json();
        if (d.success) {
          // Append new object to grid
          const grid = document.getElementById("inventoryGrid");
          if (grid) {
            renderInventory([d.object], grid);
          }
          // reset form and close
          addForm.reset();
          const formWrapper = document.getElementById("addForm");
          if (formWrapper) formWrapper.style.display = "none";
          alert("Objet ajouté");
        } else {
          alert("Erreur: " + (d.error || "Impossible d'ajouter"));
        }
      } catch (err) {
        alert("Erreur réseau");
      }
    });
  }
}

document.addEventListener("DOMContentLoaded", initAddFormListeners);

async function fetchAndRenderInventory() {
  const grid = document.getElementById("inventoryGrid");
  const catSelect = document.getElementById("categoryFilter");
  if (!grid) return;
  try {
    const res = await fetch(
      "api/database.php?action=list&database_id=" +
        encodeURIComponent(window.databaseId),
    );
    const data = await res.json();
    if (!data.success) {
      grid.innerHTML = '<div class="error">Erreur chargement</div>';
      return;
    }
    const objects = data.objects || [];
    const categories = data.categories || [];
    renderCategories(categories, catSelect);
    renderInventory(objects, grid);
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
  // Clear and add options
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
  // expose globalCategories for other functions
  window.globalCategories = roots;
}

function renderInventory(objects, gridEl) {
  gridEl.innerHTML = "";
  if (!objects.length) {
    gridEl.innerHTML =
      '<div class="empty-state">Aucun objet dans cette base.</div>';
    return;
  }
  objects.forEach((row) => {
    const hasImg = !!row.image_path;
    const card = document.createElement("div");
    card.className = "card";
    card.dataset.name = (row.nom || "").toLowerCase();
    card.dataset.cat = row.nom_categorie || "";
    card.dataset.parent = row.parent_nom || "";

    const imgContainer = document.createElement(hasImg ? "img" : "div");
    if (hasImg) {
      imgContainer.src = "uploads/" + row.image_path;
      imgContainer.setAttribute("data-action", "change-image");
      imgContainer.setAttribute("data-id", row.id);
      imgContainer.alt = "Photo objet";
      imgContainer.style.cursor = "pointer";
    } else {
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

    const del = document.createElement("a");
    del.href = "#";
    del.className = "delete-link";
    del.setAttribute("data-action", "delete");
    del.setAttribute("data-id", row.id);
    del.textContent = "🗑 Supprimer";
    details.appendChild(del);

    card.appendChild(imgContainer);
    card.appendChild(details);
    gridEl.appendChild(card);
  });
  // Re-run filter to apply current filters
  filterItems();
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
      a.href = "database-view.php?id=" + db.id;
      a.className = "btn-link";
      a.textContent = "Consulter";
      footer.appendChild(a);
      if (db.owner_id == (window.userId || "")) {
        const s2 = document.createElement("a");
        s2.href = "database-settings.php?id=" + db.id;
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
        alert("Base créée");
      } else {
        alert("Erreur: " + (d.error || "Impossible de créer"));
      }
    } catch (err) {
      alert("Erreur réseau");
    }
  });
  // fetch initial list
  fetchAndRenderDatabases();
});
