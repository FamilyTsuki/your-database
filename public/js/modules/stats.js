// c:\xampp\htdocs\your-database\public\js\modules\stats.js
export async function initStats() {
  const container = document.getElementById("globalStats");
  if (!container) return;
  try {
    const res = await fetch("api/stats");
    const d = await res.json();
    if (d.success && d.total_items > 0) {
      container.style.display = "block";
      document.getElementById("statTotalValue").textContent =
        new Intl.NumberFormat("fr-FR", {
          style: "currency",
          currency: "EUR",
        }).format(d.total_value);
      document.getElementById("statTotalItems").textContent =
        d.total_items + " objets";

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
