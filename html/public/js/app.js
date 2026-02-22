
import { apiPost } from "./utils/api.js";
import {
  showFlash,
  showPage,
  previewImage,
  initTheme,
  initGlobalUIListeners,
} from "./utils/ui.js";
import {
  initConsultationListeners,
  fetchAndRenderInventory,
  renderInventory,
  editField,
  deleteObject,
  updateQuantity,
  editFieldderoul,
} from "./modules/inventory.js";
import {
  initDashboardListeners,
  fetchAndRenderDatabases,
} from "./modules/dashboard.js";
import {
  initSettingsCollapsible,
  initSettingsListeners,
} from "./modules/settings.js";
import {
  initAddFormListeners,
  initProfileListeners,
  initGenericForms,
  checkNewCategory,
} from "./modules/forms.js";
import { initStats } from "./modules/stats.js";
import {
  openSourceChoice,
  viewFullImage,
  openObjectDetails,
  changeImage,
} from "./modules/modals.js";

window.apiPost = apiPost;
window.showFlash = showFlash;
window.showPage = showPage;
window.previewImage = previewImage;
window.checkNewCategory = checkNewCategory;
window.fetchAndRenderInventory = fetchAndRenderInventory;
window.renderInventory = renderInventory;
window.editField = editField;
window.deleteObject = deleteObject;
window.updateQuantity = updateQuantity;
window.editFieldderoul = editFieldderoul;
window.changeImage = changeImage;
window.openObjectDetails = openObjectDetails;
window.viewFullImage = viewFullImage;
window.openSourceChoice = openSourceChoice;

document.addEventListener("DOMContentLoaded", () => {
  initTheme();
  initGlobalUIListeners();
  initGenericForms();
  initAddFormListeners();
  initProfileListeners();
  initConsultationListeners();
  initSettingsCollapsible();
  initSettingsListeners();
  initDashboardListeners();
  initStats();
});
