// c:\xampp\htdocs\your-database\public\js\modules\modals.js
import { showFlash } from "../utils/ui.js";
import { apiPost } from "../utils/api.js";

export function openSourceChoice(onChoice) {
  if (window.dbSkipSourceModal) {
    onChoice(window.dbPreferGallery ? "gallery" : "camera");
    return;
  }
  const modal = document.getElementById("sourceChoiceModal");
  if (!modal) {
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

  document.body.classList.add("modal-open");
  modal.style.display = "flex";
}

export function viewFullImage(src) {
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
      if (e.target === modal) close();
    };
  }
}

export function openObjectDetails(row) {
  const modal = document.getElementById("objectDetailsModal");
  if (!modal) return;

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
    const t = parseInt(document.getElementById("detailQtyTotal").value) || 0;
    let u = parseInt(document.getElementById("detailQtyUsed").value) || 0;
    let d = parseInt(document.getElementById("detailQtyDegraded").value) || 0;

    const tInput = document.getElementById("detailQtyTotal");
    tInput.min = u + d;

    const maxUsed = Math.max(0, t - d);
    if (u > maxUsed) {
      u = maxUsed;
      document.getElementById("detailQtyUsed").value = u;
    }
    document.getElementById("detailQtyUsed").max = maxUsed;

    const maxDegraded = Math.max(0, t - u);
    if (d > maxDegraded) {
      d = maxDegraded;
      document.getElementById("detailQtyDegraded").value = d;
    }
    document.getElementById("detailQtyDegraded").max = maxDegraded;

    const available = t - u - d;
    const availInput = document.getElementById("detailQtyAvailable");
    availInput.value = available;
    availInput.style.background =
      available < 0 ? "var(--danger)" : "var(--success)";
  };

  ["detailQtyTotal", "detailQtyUsed", "detailQtyDegraded"].forEach((id) => {
    document.getElementById(id).oninput = updateAvailable;
  });
  updateAvailable();

  const form = document.getElementById("detailsForm");
  form.onsubmit = async (e) => {
    e.preventDefault();
    const t = parseInt(document.getElementById("detailQtyTotal").value) || 0;
    const u = parseInt(document.getElementById("detailQtyUsed").value) || 0;
    const d = parseInt(document.getElementById("detailQtyDegraded").value) || 0;
    if (u + d > t) {
      showFlash("Erreur: La somme dépasse le total !", "error");
      return;
    }

    const fd = new FormData(form);
    fd.append("action", "update_full");
    fd.append("csrf_token", window.csrfToken);
    fd.append("database_id", window.databaseId);

    try {
      const res = await fetch("api/database", { method: "POST", body: fd });
      const d = await res.json();
      if (d.success) {
        showFlash("Détails enregistrés", "success");
        modal.style.display = "none";
        document.body.classList.remove("modal-open");
        if (window.fetchAndRenderInventory) window.fetchAndRenderInventory();
      } else showFlash("Erreur: " + (d.error || "Erreur sauvegarde"), "error");
    } catch (err) {
      showFlash("Erreur réseau", "error");
    }
  };

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

export function changeImage(id) {
  openSourceChoice((source) => {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";
    if (source === "camera") input.setAttribute("capture", "environment");

    input.onchange = (e) => {
      const file = e.target.files[0];
      if (!file) return;

      // 1. Création dynamique de la modale de crop si elle n'existe pas
      let cropModal = document.getElementById("cropModalDynamic");
      if (!cropModal) {
        cropModal = document.createElement("div");
        cropModal.id = "cropModalDynamic";
        cropModal.className = "modal";
        cropModal.style.display = "none";
        cropModal.innerHTML = `
          <div class="modal-content" style="width: 95%; max-width: 500px;">
            <span class="close" id="closeCropDynamic">&times;</span>
            <h2 style="margin-top:0">Ajuster l'image</h2>
            <div style="max-height: 50vh; overflow: hidden; background:#000;">
              <img id="cropImageTarget" style="max-width: 100%; display:block;">
            </div>
            <button id="btnValidateCrop" class="btn btn-primary" style="width:100%; margin-top:15px;">Valider et Enregistrer</button>
          </div>`;
        document.body.appendChild(cropModal);
      }

      const imgTarget = document.getElementById("cropImageTarget");
      const btnValidate = document.getElementById("btnValidateCrop");
      const btnClose = document.getElementById("closeCropDynamic");
      let cropper = null;

      const closeCrop = () => {
        if (cropper) cropper.destroy();
        cropModal.style.display = "none";
        document.body.classList.remove("modal-open");
      };

      // 2. Chargement de l'image dans le cropper
      const reader = new FileReader();
      reader.onload = (ev) => {
        imgTarget.src = ev.target.result;
        cropModal.style.display = "flex";
        document.body.classList.add("modal-open");
        cropper = new Cropper(imgTarget, { aspectRatio: 1, viewMode: 1 });
      };
      reader.readAsDataURL(file);

      // 3. Gestion de la validation
      // On clone le bouton pour supprimer les anciens écouteurs d'événements
      const newBtn = btnValidate.cloneNode(true);
      btnValidate.parentNode.replaceChild(newBtn, btnValidate);

      newBtn.onclick = () => {
        if (!cropper) return;
        newBtn.textContent = "Envoi en cours...";
        cropper.getCroppedCanvas({ width: 800, height: 800 }).toBlob(
          async (blob) => {
            const fd = new FormData();
            fd.append("action", "updateImage");
            fd.append("id", id);
            fd.append("csrf_token", window.csrfToken);
            fd.append("database_id", window.databaseId);
            fd.append("image", blob, "photo.jpg");

            try {
              const res = await fetch("api/database", {
                method: "POST",
                body: fd,
              });
              const d = await res.json();
              if (d.success) location.reload();
              else {
                showFlash("Erreur: " + (d.error || "upload"), "error");
                newBtn.textContent = "Valider et Enregistrer";
              }
            } catch (err) {
              showFlash("Erreur réseau", "error");
              newBtn.textContent = "Valider et Enregistrer";
            }
          },
          "image/jpeg",
          0.9,
        );
      };

      btnClose.onclick = closeCrop;
      cropModal.onclick = (evt) => {
        if (evt.target === cropModal) closeCrop();
      };
    };
    input.click();
  });
}
