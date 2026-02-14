// c:\xampp\htdocs\your-database\public\js\modules\dashboard.js
import { apiPost } from "../utils/api.js";
import { showFlash } from "../utils/ui.js";

export async function fetchAndRenderDatabases() {
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
        '<div class="no-databases"><p>Vous n\'avez aucune base de données.</p></div>';
      return;
    }
    list.forEach((db) => {
      const card = document.createElement("div");
      card.className = "database-card";

      let badges = "";
      if (db.owner_id == (window.userId || ""))
        badges += '<span class="badge-owner">Propriétaire</span>';
      else if (db.permission)
        badges += `<span class="badge-permission">${db.permission}</span>`;

      card.innerHTML = `
        <div class="db-card-header"><h3>${db.name}</h3>${badges}<a href="export.php?id=${db.id}" class="btn-icon" target="_blank" title="Exporter">⬇️</a></div>
        
        ${db.description ? `<p class="db-description">${db.description}</p>` : '<p class="db-description"></p>'}
        <div class="db-card-footer">
            <a href="database-view.php?id=${db.id}" class="btn-link">Consulter</a>
            
            ${db.owner_id == (window.userId || "") ? `<a href="database-settings.php?id=${db.id}" class="btn-link">Paramètres</a>` : ""}
        </div>
      `;
      grid.appendChild(card);
    });
  } catch (e) {
    grid.innerHTML = '<div class="error">Erreur réseau</div>';
  }
}

export function initDashboardListeners() {
  const createForm = document.getElementById("createDbForm");
  if (createForm) {
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
          fetchAndRenderDatabases();
          createForm.reset();
          const wrapper = document.getElementById("createForm");
          if (wrapper) wrapper.style.display = "none";
          showFlash("Base créée", "success");
        } else
          showFlash("Erreur: " + (d.error || "Impossible de créer"), "error");
      } catch (err) {
        showFlash("Erreur réseau", "error");
      }
    });
  }

  document.addEventListener("click", function (e) {
    const el = e.target.closest("[data-action='toggle-create']");
    if (el) {
      const form = document.getElementById("createForm");
      if (form) {
        form.style.display =
          form.style.display === "none" || form.style.display === ""
            ? "block"
            : "none";
        const name = document.getElementById("name");
        if (name) name.focus();
      }
    }
  });

  fetchAndRenderDatabases();
}
