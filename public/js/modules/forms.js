// c:\xampp\htdocs\your-database\public\js\modules\forms.js
import { apiPost } from "../utils/api.js";
import { showFlash } from "../utils/ui.js";
import { openSourceChoice } from "./modals.js";
import { renderInventory } from "./inventory.js";

export function checkNewCategory(select) {
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

export function initAddFormListeners() {
  const dropZone = document.getElementById("dropZone");
  const fileInput = document.getElementById("fileInput");
  const previewImage = document.getElementById("previewImage");
  const placeholder = document.getElementById("placeholder");

  if (dropZone && fileInput) {
    fileInput.addEventListener("click", (e) => e.stopPropagation());
    dropZone.addEventListener("click", () =>
      openSourceChoice((src) => {
        if (src === "camera") fileInput.setAttribute("capture", "environment");
        else fileInput.removeAttribute("capture");
        fileInput.click();
      }),
    );
    dropZone.addEventListener("dragover", (e) => {
      e.preventDefault();
      dropZone.classList.add("drag-active");
      dropZone.style.background = "#ecf0f1";
    });
    dropZone.addEventListener("dragleave", (e) => {
      e.preventDefault();
      dropZone.style.background = "#f8f9fa";
    });
    dropZone.addEventListener("drop", (e) => {
      e.preventDefault();
      dropZone.style.background = "#f8f9fa";
      if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        handlePreview(e.dataTransfer.files[0]);
      }
    });
    fileInput.addEventListener("change", (e) => {
      if (e.target.files.length) handlePreview(e.target.files[0]);
    });
  }

  function handlePreview(file) {
    if (!file || !file.type || !file.type.startsWith("image/")) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
      if (previewImage) {
        previewImage.src = ev.target.result;
        previewImage.style.display = "block";
      }
      if (placeholder) placeholder.style.display = "none";
      dropZone.style.border = "2px dashed #3498db";
    };
    reader.readAsDataURL(file);
  }

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
          if (window.dbRedirectOnAdd) {
            window.location.href = "database/" + window.databaseId;
            return;
          }
          const grid = document.getElementById("inventoryGrid");
          if (grid) renderInventory([d.object], grid);
          addForm.reset();
          const wrapper = document.getElementById("addForm");
          if (wrapper) wrapper.style.display = "none";
          showFlash("Objet ajouté", "success");
        } else
          showFlash("Erreur: " + (d.error || "Impossible d'ajouter"), "error");
      } catch (err) {
        showFlash("Erreur réseau", "error");
      }
    });
  }

  document.addEventListener("click", function (e) {
    const el = e.target.closest("[data-action='toggle-add']");
    if (el) {
      const form = document.getElementById("addForm");
      if (form) {
        form.style.display =
          form.style.display === "none" || form.style.display === ""
            ? "block"
            : "none";
        const inp = form.querySelector("input, textarea, select");
        if (inp) inp.focus();
      }
    }
  });

  // Initialisation des sélecteurs de catégories dynamiques (page Ajouter)
  initCategorySelects();
}

function initCategorySelects() {
  const mainSelect = document.getElementById("main_category_select");
  const subSelect = document.getElementById("sub_category_select");
  const subContainer = document.getElementById("sub_category_container");

  if (!mainSelect || !subSelect || !subContainer) return;

  mainSelect.addEventListener("change", async function () {
    const parentId = this.value;

    // Réinitialiser le menu des sous-catégories
    subSelect.innerHTML = "";

    if (parentId === "NEW") {
      subContainer.style.display = "none";
      const newName = prompt("Nom de la nouvelle catégorie :");
      if (newName && newName.trim() !== "") {
        try {
          const d = await apiPost({
            action: "create_category",
            name: newName,
            parent_id: 0,
            csrf_token: window.csrfToken,
            database_id: window.databaseId,
          });
          if (d && d.success) {
            // 1. Mise à jour des données globales
            const newCat = {
              id: d.id,
              nom: newName,
              parent_id: null,
              subs: [],
            };
            if (window.globalCategories) window.globalCategories.push(newCat);

            // 2. Ajout visuel
            const opt = new Option(newName, d.id);
            this.add(opt, this.options.length - 1);

            // 3. Sélection
            this.value = d.id;
            this.dispatchEvent(new Event("change"));
          } else {
            showFlash(
              "Erreur: " + (d.message || d.error || "Impossible de créer"),
              "error",
            );
            this.value = "0";
          }
        } catch (e) {
          showFlash("Erreur réseau", "error");
          this.value = "0";
        }
      } else {
        this.value = "0";
      }
      return;
    }

    if (parentId === "0") {
      subContainer.style.display = "none";
      return;
    }

    // Trouver les données de la catégorie parente
    const parentData = window.globalCategories
      ? window.globalCategories.find((c) => c.id == parentId)
      : null;

    if (parentData) {
      let optionsHtml = `<option value="${parentId}">-- Utiliser la catégorie principale --</option>`;

      if (parentData.subs && parentData.subs.length > 0) {
        parentData.subs.forEach((sub) => {
          optionsHtml += `<option value="${sub.id}">${sub.nom}</option>`;
        });
      }
      optionsHtml += `<option value="NEW_SUB:${parentId}" class="option-new">+ Ajouter une sous-catégorie</option>`;

      subSelect.innerHTML = optionsHtml;
      subContainer.style.display = "block";
    } else {
      subContainer.style.display = "none";
    }
  });

  subSelect.addEventListener("change", async function () {
    if (this.value && this.value.indexOf("NEW_SUB:") === 0) {
      const parts = this.value.split(":");
      const parentId = parseInt(parts[1], 10);
      const newName = prompt("Nom de la nouvelle sous-catégorie :");
      if (newName && newName.trim() !== "") {
        try {
          const d = await apiPost({
            action: "create_category",
            name: newName,
            parent_id: parentId,
            csrf_token: window.csrfToken,
            database_id: window.databaseId,
          });
          if (d && d.success) {
            const parentCat = window.globalCategories.find(
              (c) => c.id == parentId,
            );
            if (parentCat) {
              if (!parentCat.subs) parentCat.subs = [];
              parentCat.subs.push({
                id: d.id,
                nom: newName,
                parent_id: parentId,
              });
            }
            const opt = new Option(newName, d.id);
            this.add(opt, this.options.length - 1);
            this.value = d.id;
          } else {
            showFlash(
              "Erreur: " + (d.message || d.error || "Impossible de créer"),
              "error",
            );
            this.value = parentId;
          }
        } catch (e) {
          showFlash("Erreur réseau", "error");
          this.value = parentId;
        }
      } else {
        this.value = parentId;
      }
    }
  });
}

export function initProfileListeners() {
  const profileForm = document.getElementById("profileForm");
  const profileInput = document.getElementById("profileImageInput");
  const profilePreview = document.getElementById("profileAvatarPreview");

  if (profileInput && profilePreview) {
    profileInput.addEventListener("change", function (e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => {
          if (profilePreview.tagName === "IMG")
            profilePreview.src = ev.target.result;
          else {
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
        const d = await res.json();
        if (d.success) {
          showFlash("Profil mis à jour !", "success");
          setTimeout(() => location.reload(), 1000);
        } else showFlash("Erreur : " + d.message, "error");
      } catch (err) {
        showFlash("Erreur réseau", "error");
      }
    });
  }
}

export function initGenericForms() {
  document.querySelectorAll('form[data-ajax="true"]').forEach((form) => {
    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      const fd = new FormData(form);
      if (!fd.has("csrf_token") && window.csrfToken)
        fd.append("csrf_token", window.csrfToken);
      try {
        const res = await fetch(
          form.getAttribute("action") || window.location.href,
          { method: "POST", body: fd },
        );
        if (res.redirected) {
          window.location = res.url;
          return;
        }
        if (res.ok) window.location.reload();
        else showFlash("Erreur: " + (await res.text()), "error");
      } catch (err) {
        showFlash("Erreur réseau", "error");
      }
    });
  });
}
