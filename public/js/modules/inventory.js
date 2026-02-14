// c:\xampp\htdocs\your-database\public\js\modules\inventory.js
import { apiPost } from "../utils/api.js";
import { showFlash } from "../utils/ui.js";
import { openObjectDetails, changeImage, viewFullImage } from "./modals.js";

window.currentPage = 1;
window.itemsPerPage = 50;
let searchTimeout;

export function filterItems() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    window.currentPage = 1;
    fetchAndRenderInventory();
  }, 300);
}

export async function fetchAndRenderInventory() {
  const grid = document.getElementById("inventoryGrid");
  const catSelect = document.getElementById("categoryFilter");
  const searchInput = document.getElementById("searchInput");
  const sortSelect = document.getElementById("sortOrder");

  if (!grid) return;

  const search = searchInput ? searchInput.value.trim() : "";
  const category = catSelect ? catSelect.value : "";
  const sort = sortSelect ? sortSelect.value : "date_desc";

  try {
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

    renderCategories(data.categories || [], catSelect);
    setupSortDropdown();
    setupLimitSelector();
    renderInventory(data.objects || [], grid);
    renderPagination(data.total || (data.objects || []).length);
  } catch (e) {
    grid.innerHTML = '<div class="error">Erreur réseau</div>';
  }
}

// C'est ici que l'export est crucial
export function renderInventory(objects, gridEl) {
  gridEl.innerHTML = "";
  if (!objects.length) {
    gridEl.innerHTML =
      '<div class="empty-state" onclick="window.location.href=\'database-ajouter.php?id=' +
      window.databaseId +
      '\'" style="cursor: pointer;">Aucun objet dans cette base.<br><strong>Cliquez ici pour en ajouter un.</strong></div>';
    return;
  }
  objects.forEach((row) => {
    const hasImg = !!row.image_path;
    const card = document.createElement("div");
    card.className = "card";

    let imgContainer = document.createElement("div");
    if (hasImg) {
      imgContainer.className = "card-image-wrapper";
      imgContainer.setAttribute("data-action", "change-image");
      imgContainer.setAttribute("data-id", row.id);
      imgContainer.innerHTML = `<img src="uploads/${row.image_path}" alt="Photo"><div class="view-image-icon" title="Voir">🔍</div>`;
      imgContainer.querySelector(".view-image-icon").onclick = (e) => {
        e.stopPropagation();
        viewFullImage("uploads/" + row.image_path);
      };
    } else {
      imgContainer.className = "card-no-image";
      imgContainer.setAttribute("data-action", "change-image");
      imgContainer.setAttribute("data-id", row.id);
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
    h3.textContent = row.nom;
    details.appendChild(h3);

    const infoIcon = document.createElement("div");
    infoIcon.className = "info-icon";
    infoIcon.innerHTML = "🛈";
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
    qtyZone.innerHTML = `
        <button class="btn-qty" data-action="qty-dec" data-id="${row.id}">-</button>
        <span class="qty-val" id="qty-${row.id}" data-action="edit-field" data-id="${row.id}" data-field="quantite" data-value="${row.quantite}" style="cursor:pointer;border-bottom:2px dashed #3498db">${row.quantite}</span>
        <button class="btn-qty" data-action="qty-inc" data-id="${row.id}">+</button>
    `;
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

function renderCategories(categories, selectEl) {
  if (!selectEl) return;
  const map = {};
  categories.forEach((c) => (map[c.id] = Object.assign({}, c, { subs: [] })));
  const roots = [];
  categories.forEach((c) => {
    if (c.parent_id == null) roots.push(map[c.id]);
    else if (map[c.parent_id]) map[c.parent_id].subs.push(map[c.id]);
  });
  window.globalCategories = roots;

  selectEl.innerHTML = '<option value="">Toutes les catégories</option>';
  const optNone = document.createElement("option");
  optNone.value = "NULL";
  optNone.textContent = "Sans catégorie";
  selectEl.appendChild(optNone);

  roots.forEach((p) => {
    selectEl.add(new Option(p.nom, p.nom));
    if (p.subs)
      p.subs.forEach((s) => selectEl.add(new Option("↳ " + s.nom, s.nom)));
  });

  selectEl.style.display = "none";
  let trigger = document.getElementById("categoryFilterTrigger");
  if (!trigger) {
    trigger = document.createElement("div");
    trigger.id = "categoryFilterTrigger";
    trigger.className = "form-input";
    Object.assign(trigger.style, {
      cursor: "pointer",
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      minWidth: "250px",
    });
    trigger.innerHTML =
      '<span id="catFilterLabel">Toutes les catégories</span> <span style="font-size: 0.8em">▼</span>';
    if (selectEl.parentNode)
      selectEl.parentNode.insertBefore(trigger, selectEl.nextSibling);
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
  Object.assign(menu.style, {
    position: "absolute",
    top: window.scrollY + rect.bottom + 5 + "px",
    left: window.scrollX + rect.left + "px",
    width: rect.width + "px",
    zIndex: 2000,
    maxHeight: "300px",
  });

  const addOption = (text, val) => {
    const div = document.createElement("div");
    div.className = "cat-option";
    div.textContent = text;
    div.onclick = () => {
      selectEl.value = val;
      document.getElementById("catFilterLabel").textContent = text;
      filterItems();
      menu.remove();
    };
    menu.appendChild(div);
  };

  addOption("Toutes les catégories", "");
  addOption("Sans catégorie", "NULL");

  if (window.globalCategories) {
    window.globalCategories.forEach((p) => {
      const parentContainer = document.createElement("div");
      parentContainer.style.borderBottom = "1px solid var(--border)";

      const parentHeader = document.createElement("div");
      parentHeader.className = "cat-parent";
      parentHeader.innerHTML = `<span class="cat-arrow">▼</span><span style="flex:1">${p.nom}</span>`;

      const childrenContainer = document.createElement("div");
      childrenContainer.className = "cat-children";

      let isExpanded = false;
      parentHeader.onclick = (e) => {
        e.stopPropagation();
        isExpanded = !isExpanded;
        childrenContainer.style.display = isExpanded ? "block" : "none";
        parentHeader.querySelector(".cat-arrow").style.transform = isExpanded
          ? "rotate(180deg)"
          : "rotate(0deg)";
      };

      const selectParent = document.createElement("div");
      selectParent.textContent = "Sélectionner: " + p.nom;
      Object.assign(selectParent.style, {
        padding: "10px 12px 10px 30px",
        cursor: "pointer",
        borderBottom: "1px solid var(--border)",
        fontSize: "13px",
      });
      selectParent.onclick = () => {
        selectEl.value = p.nom;
        document.getElementById("catFilterLabel").textContent = p.nom;
        filterItems();
        menu.remove();
      };
      childrenContainer.appendChild(selectParent);

      if (p.subs) {
        p.subs.forEach((s) => {
          const sub = document.createElement("div");
          sub.textContent = s.nom;
          Object.assign(sub.style, {
            padding: "9px 12px 9px 40px",
            cursor: "pointer",
            borderBottom: "1px solid var(--border)",
            color: "var(--text-muted)",
            fontSize: "13px",
          });
          sub.onclick = () => {
            selectEl.value = s.nom;
            document.getElementById("catFilterLabel").textContent = s.nom;
            filterItems();
            menu.remove();
          };
          childrenContainer.appendChild(sub);
        });
      }
      parentContainer.appendChild(parentHeader);
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

function setupSortDropdown() {
  const searchZone = document.querySelector(".search-zone");
  if (!searchZone || document.getElementById("sortOrder")) return;
  const select = document.createElement("select");
  select.id = "sortOrder";
  [
    { val: "date_desc", text: "📅 Plus récent" },
    { val: "date_asc", text: "📅 Plus ancien" },
    { val: "alpha_asc", text: "🔤 Nom (A - Z)" },
    { val: "alpha_desc", text: "🔤 Nom (Z - A)" },
    { val: "qty_desc", text: "🔢 Quantité ( - )" },
    { val: "qty_asc", text: "🔢 Quantité ( + )" },
  ].forEach((opt) => select.add(new Option(opt.text, opt.val)));
  select.addEventListener("change", () => {
    window.currentPage = 1;
    fetchAndRenderInventory();
  });
  searchZone.appendChild(select);
}

function setupLimitSelector() {
  const searchZone = document.querySelector(".search-zone");
  if (!searchZone || document.getElementById("limitSelector")) return;
  const select = document.createElement("select");
  select.id = "limitSelector";
  [20, 50, 100, 200].forEach((val) => {
    const o = new Option(val + " / page", val);
    if (val === window.itemsPerPage) o.selected = true;
    select.add(o);
  });
  select.addEventListener("change", () => {
    window.itemsPerPage = parseInt(select.value);
    window.currentPage = 1;
    fetchAndRenderInventory();
  });
  const sort = document.getElementById("sortOrder");
  if (sort) searchZone.insertBefore(select, sort);
  else searchZone.appendChild(select);
}

function renderPagination(totalItems) {
  let container = document.getElementById("paginationContainer");
  const grid = document.getElementById("inventoryGrid");
  if (!container) {
    container = document.createElement("div");
    container.id = "paginationContainer";
    container.className = "pagination-controls";
    if (grid && grid.parentNode)
      grid.parentNode.insertBefore(container, grid.nextSibling);
  }
  container.innerHTML = "";
  const totalPages = Math.ceil(totalItems / window.itemsPerPage);
  if (totalPages <= 1) {
    container.style.display = "none";
    return;
  }
  container.style.display = "flex";

  const createBtn = (text, disabled, onClick) => {
    const btn = document.createElement("button");
    btn.className = "pagination-btn";
    btn.textContent = text;
    btn.disabled = disabled;
    btn.onclick = onClick;
    return btn;
  };

  container.appendChild(
    createBtn("←", window.currentPage <= 1, () => {
      window.currentPage--;
      fetchAndRenderInventory();
    }),
  );
  const info = document.createElement("span");
  info.className = "pagination-info";
  info.textContent = `Page ${window.currentPage} / ${totalPages}`;
  container.appendChild(info);
  container.appendChild(
    createBtn("→", window.currentPage >= totalPages, () => {
      window.currentPage++;
      fetchAndRenderInventory();
    }),
  );
}

export function editField(id, field, currentValue) {
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

export function editFieldderoul(id, field, currentValue, event) {
  const target = event && event.currentTarget;
  if (!target || target.dataset.catMenuOpen === "1") return;
  target.dataset.catMenuOpen = "1";

  const menu = document.createElement("div");
  menu.className = "category-tree-menu";

  const addOpt = (text, cb, cls = "cat-option") => {
    const d = document.createElement("div");
    d.className = cls;
    d.textContent = text;
    d.onclick = cb;
    return d;
  };

  menu.appendChild(addOpt("-- Sans catégorie --", () => selectCategory("0")));

  if (window.globalCategories) {
    window.globalCategories.forEach((p) => {
      const parentContainer = document.createElement("div");
      parentContainer.style.borderBottom = "1px solid #ecf0f1";

      const parentHeader = document.createElement("div");
      parentHeader.className = "cat-parent";
      parentHeader.innerHTML = `<span class="cat-arrow">▼</span><span style="flex:1">${p.nom}</span>`;

      const children = document.createElement("div");
      children.className = "cat-children";
      let expanded = false;
      parentHeader.onclick = () => {
        expanded = !expanded;
        children.style.display = expanded ? "block" : "none";
        parentHeader.querySelector(".cat-arrow").style.transform = expanded
          ? "rotate(180deg)"
          : "rotate(0deg)";
      };

      const selP = document.createElement("div");
      selP.textContent = "Sélectionner: " + p.nom;
      Object.assign(selP.style, {
        padding: "10px 12px 10px 30px",
        cursor: "pointer",
        borderBottom: "1px solid var(--border)",
        fontSize: "13px",
      });
      selP.onclick = () => selectCategory(p.id);
      children.appendChild(selP);

      if (p.subs)
        p.subs.forEach((s) => {
          const sub = document.createElement("div");
          sub.textContent = s.nom;
          Object.assign(sub.style, {
            padding: "9px 12px 9px 40px",
            cursor: "pointer",
            borderBottom: "1px solid var(--border)",
            color: "var(--text-muted)",
            fontSize: "13px",
          });
          sub.onclick = () => selectCategory(s.id);
          children.appendChild(sub);
        });

      const addSub = document.createElement("div");
      addSub.className = "btn-add-sub";
      addSub.textContent = "+ New SubCategory";
      addSub.onclick = () => {
        const n = prompt('Nom de la sous-catégorie sous "' + p.nom + '":');
        if (n && n.trim())
          apiPost({
            action: "edit",
            id,
            field: "new_subcategory_create",
            value: n,
            parent_id: p.id,
            csrf_token: window.csrfToken,
            database_id: window.databaseId,
          }).then((d) =>
            d.success ? location.reload() : showFlash("Erreur", "error"),
          );
      };
      children.appendChild(addSub);

      parentContainer.appendChild(parentHeader);
      parentContainer.appendChild(children);
      menu.appendChild(parentContainer);
    });
  }

  menu.appendChild(
    addOpt(
      "+ New Category",
      () => {
        const n = prompt("Nom de la nouvelle catégorie :");
        if (n && n.trim())
          apiPost({
            action: "edit",
            id,
            field: "new_category_create",
            value: n,
            csrf_token: window.csrfToken,
            database_id: window.databaseId,
          }).then((d) =>
            d.success ? location.reload() : showFlash("Erreur", "error"),
          );
      },
      "btn-new-cat",
    ),
  );

  const selectCategory = (catId) => {
    apiPost({
      action: "edit",
      id,
      field: "id_categorie",
      value: catId,
      csrf_token: window.csrfToken,
      database_id: window.databaseId,
    }).then((d) =>
      d.success ? location.reload() : showFlash("Erreur", "error"),
    );
  };

  document.body.appendChild(menu);
  const rect = target.getBoundingClientRect();
  Object.assign(menu.style, {
    top: window.scrollY + rect.bottom + "px",
    left: window.scrollX + rect.left + "px",
    width: Math.max(200, rect.width) + "px",
  });
  menu.tabIndex = 0;
  menu.focus();
  menu.onblur = () => {
    setTimeout(() => {
      menu.remove();
      delete target.dataset.catMenuOpen;
    }, 150);
  };
}

export function deleteObject(id) {
  if (!confirm("Supprimer cet objet ?")) return;
  apiPost({
    action: "delete",
    id,
    csrf_token: window.csrfToken,
    database_id: window.databaseId,
  })
    .then((d) =>
      d.success ? location.reload() : showFlash("Erreur suppression", "error"),
    )
    .catch(() => showFlash("Erreur réseau", "error"));
}

let isUpdatingQuantity = false;
export async function updateQuantity(id, action) {
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

export function initConsultationListeners() {
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
  fetchAndRenderInventory();
}
