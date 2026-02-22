
export function showPage(id) {
  const c = document.getElementById("page-consultation");
  const a = document.getElementById("page-ajout");
  if (c) c.style.display = id === "consultation" ? "block" : "none";
  if (a) a.style.display = id === "ajout" ? "block" : "none";
}

export function showFlash(message, type = "info") {
  const div = document.createElement("div");
  div.className = `flash-message flash-${type}`;

  const text = document.createElement("span");
  text.textContent = message;

  const btn = document.createElement("button");
  btn.innerHTML = "&times;";
  Object.assign(btn.style, {
    position: "absolute",
    top: "50%",
    right: "10px",
    transform: "translateY(-50%)",
    background: "none",
    border: "none",
    fontSize: "20px",
    cursor: "pointer",
    color: "inherit",
  });

  btn.onclick = () => {
    div.classList.add("fade-out");
    setTimeout(() => div.remove(), 500);
  };

  div.appendChild(text);
  div.appendChild(btn);
  document.body.appendChild(div);

  setTimeout(() => {
    if (document.body.contains(div)) {
      div.classList.add("fade-out");
      setTimeout(() => {
        if (document.body.contains(div)) div.remove();
      }, 500);
    }
  }, 4000);
}

export function previewImage(event) {
  const file = event.target.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    showFlash("L'image est trop volumineuse (max 5MB)", "error");
    event.target.value = "";
    return;
  }
  const allowedTypes = ["image/jpeg", "image/png", "image/webp", "image/gif"];
  if (!allowedTypes.includes(file.type)) {
    showFlash("Format d'image non autorisé.", "error");
    event.target.value = "";
    return;
  }
  const reader = new FileReader();
  reader.onload = function () {
    const output = document.getElementById("imagePreview");
    const content = document.getElementById("placeholder-content");
    if (output) {
      output.src = reader.result;
      output.style.display = "block";
    }
    if (content) content.style.display = "none";
    const dropZone = document.getElementById("drop-zone");
    if (dropZone) dropZone.style.border = "none";
  };
  reader.readAsDataURL(file);
}

export function initTheme() {
  const toggle = document.getElementById("theme-toggle");
  const stored = localStorage.getItem("theme");
  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  const isDark = stored === "dark" || (!stored && prefersDark);
  document.documentElement.setAttribute(
    "data-theme",
    isDark ? "dark" : "light",
  );

  if (toggle) {
    if (toggle.type === "checkbox") {
      toggle.checked = isDark;
      toggle.addEventListener("change", () => {
        const next = toggle.checked ? "dark" : "light";
        document.documentElement.setAttribute("data-theme", next);
        localStorage.setItem("theme", next);
      });
    } else {
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

export function initGlobalUIListeners() {
  document.addEventListener(
    "click",
    function (e) {
      // Flash close
      const flashClose = e.target.closest("[data-action='flash-close']");
      if (flashClose) {
        const flash = flashClose.closest(".flash-message");
        if (flash) {
          flash.classList.add("fade-out");
          setTimeout(() => (flash.style.display = "none"), 300);
        }
      }
      // Confirm dialog
      const conf = e.target.closest("[data-confirm]");
      if (conf) {
        const msg = conf.dataset.confirm || "Confirmer ?";
        if (!confirm(msg)) {
          e.preventDefault();
          e.stopPropagation();
        }
      }
    },
    true,
  );
}
