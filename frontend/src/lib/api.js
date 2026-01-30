export async function apiGet(url) {
  const res = await fetch(url, { method: "GET", headers: { Accept: "application/json" } });
  const txt = await res.text();

  let json;
  try {
    json = JSON.parse(txt);
  } catch {
    json = { raw: txt };
  }

  if (!res.ok) {
    const msg = json?.message || json?.error || txt || `HTTP ${res.status}`;
    throw new Error(msg);
  }

  return json;
}

export async function apiPost(url, body) {
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: body ? JSON.stringify(body) : null,
  });
  const txt = await res.text();

  let json;
  try {
    json = JSON.parse(txt);
  } catch {
    json = { raw: txt };
  }

  if (!res.ok) {
    const msg = json?.message || json?.error || txt || `HTTP ${res.status}`;
    throw new Error(msg);
  }

  return json;
}







