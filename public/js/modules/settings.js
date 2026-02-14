// c:\xampp\htdocs\your-database\public\js\modules\settings.js
import { apiPost } from "../utils/api.js";
import { showFlash } from "../utils/ui.js";

export function initSettingsCollapsible() {
  const sections = document.querySelectorAll(".settings-section");
  sections.forEach((section) => {
    if (section.dataset.collapsible === "1") return;
    const h2 = section.querySelector("h2");
    if (!h2) return;

    const header = document.createElement("div");
    header.className = "section-header";
    header.tabIndex = 0;
    header.innerHTML = `<span class="section-arrow box">▸</span>`;
    h2.classList.add("box");
    header.prepend(h2);

    const body = document.createElement("div");
    body.className = "section-body";
    while (section.firstChild) body.appendChild(section.firstChild);

    section.appendChild(header);
    section.appendChild(body);

    section.classList.add("collapsed");
    body.style.display = "none";
    section.dataset.collapsible = "1";

    const toggle = () => {
      const nowCollapsed = section.classList.toggle("collapsed");
      body.style.display = nowCollapsed ? "none" : "";
      header.querySelector(".section-arrow").textContent = nowCollapsed
        ? "▸"
        : "▾";
    };
    header.addEventListener("click", toggle);
    header.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        toggle();
      }
    });
  });
}

export function initSettingsListeners() {
  document.addEventListener("click", function (e) {
    const el = e.target.closest("[data-action]");
    if (!el) return;
    const action = el.dataset.action;

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
            showFlash("Enregistré", "success");
            setTimeout(() => location.reload(), 1000);
          } else showFlash("Erreur: " + d.error, "error");
        })
        .catch(() => showFlash("Erreur réseau", "error"));
    }

    if (action === "add-user") {
      const form = document.getElementById("addUserForm");
      if (!form) return;
      apiPost({
        action: "add_user",
        username: form.querySelector("#username")?.value,
        permission: form.querySelector("#permission")?.value,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      }).then((d) => {
        if (d.success) {
          showFlash("Ajouté", "success");
          setTimeout(() => location.reload(), 1000);
        } else showFlash("Erreur: " + d.error, "error");
      });
    }

    if (action === "remove-user") {
      apiPost({
        action: "remove_user",
        permission_id: el.dataset.permissionId,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      }).then((d) => {
        if (d.success) el.closest("tr")?.remove() || location.reload();
        else showFlash("Erreur", "error");
      });
    }

    if (action === "rename-category") {
      const newName = document
        .getElementById("cat-name-" + el.dataset.categoryId)
        ?.value.trim();
      if (!newName) return showFlash("Nom requis", "error");
      apiPost({
        action: "rename_category",
        category_id: el.dataset.categoryId,
        new_name: newName,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      }).then((d) => {
        if (d.success) {
          showFlash("Renommé", "success");
          setTimeout(() => location.reload(), 1000);
        } else showFlash("Erreur", "error");
      });
    }

    if (action === "delete-category") {
      apiPost({
        action: "delete_category",
        category_id: el.dataset.categoryId,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      }).then((d) => {
        if (d.success)
          (el.closest("li") || el.closest("div"))?.remove() ||
            location.reload();
        else showFlash("Erreur", "error");
      });
    }

    if (action === "add-root-category") {
      const name = document.getElementById("new-root-cat-name")?.value.trim();
      if (name)
        apiPost({
          action: "create_category",
          name,
          parent_id: 0,
          csrf_token: window.csrfToken,
          database_id: window.databaseId,
        }).then((d) =>
          d.success ? location.reload() : showFlash("Erreur", "error"),
        );
    }

    if (action === "add-subcategory") {
      const name = document
        .getElementById("subcat-input-" + el.dataset.parentId)
        ?.value.trim();
      if (name)
        apiPost({
          action: "add_subcategory",
          parent_id: el.dataset.parentId,
          name,
          csrf_token: window.csrfToken,
          database_id: window.databaseId,
        }).then((d) =>
          d.success
            ? (showFlash("Ajouté", "success"),
              setTimeout(() => location.reload(), 1000))
            : showFlash("Erreur", "error"),
        );
    }

    if (action === "delete-database") {
      if (document.getElementById("confirmCheck")?.checked) {
        apiPost({
          action: "delete_database",
          confirm: "yes",
          csrf_token: window.csrfToken,
          database_id: window.databaseId,
        }).then((d) => {
          if (d.success) {
            showFlash("Supprimée", "success");
            setTimeout(() => (window.location = "index"), 1000);
          } else showFlash("Erreur", "error");
        });
      } else showFlash("Cochez la case pour confirmer", "error");
    }
  });

  document.addEventListener("change", function (e) {
    const el = e.target.closest("[data-action='update-permission']");
    if (el) {
      apiPost({
        action: "update_permission",
        user_id: el.dataset.userId,
        new_permission: el.value,
        csrf_token: window.csrfToken,
        database_id: window.databaseId,
      }).then((d) => {
        if (!d.success) {
          showFlash("Erreur", "error");
          setTimeout(() => location.reload(), 1000);
        } else el.className = "badge badge-" + el.value + " permission-toggle";
      });
    }
  });
}
