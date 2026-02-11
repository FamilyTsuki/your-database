// c:\xampp\htdocs\your-database\public\js\utils\api.js
export async function apiPost(data) {
  const params = new URLSearchParams();
  for (const k in data) params.append(k, data[k]);
  const res = await fetch("api/database.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: params.toString(),
  });
  return res.json();
}
