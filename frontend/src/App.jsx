import { useState } from "react";

export default function App() {
  const [message, setMessage] = useState(
    "Je cherche 5 développeurs Java Spring React à Rabat"
  );
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState(null);
  const [error, setError] = useState("");
  const [actionMsg, setActionMsg] = useState("");

  const apiPost = async (url, body) => {
    const res = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: body ? JSON.stringify(body) : null,
    });

    if (!res.ok) {
      const txt = await res.text();
      throw new Error(`HTTP ${res.status} - ${txt}`);
    }
    return res.json();
  };

  const apiGet = async (url) => {
    const res = await fetch(url, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    if (!res.ok) {
      const txt = await res.text();
      throw new Error(`HTTP ${res.status} - ${txt}`);
    }
    return res.json();
  };

  const runSearch = async () => {
    setLoading(true);
    setError("");
    setActionMsg("");
    setData(null);

    try {
      const json = await apiPost("/api/chat/search", { message });
      setData(json); // json contient id_demande + results initiaux
    } catch (e) {
      setError(e.message || "Erreur inconnue");
    } finally {
      setLoading(false);
    }
  };

  // ✅ refresh SANS créer une nouvelle demande
  const refreshResults = async () => {
    if (!data?.id_demande) return;
    try {
      const json = await apiGet(`/api/demander/${data.id_demande}`);
      setData((prev) => ({
        ...prev,
        // on garde le message saisi + criteria déjà retournés si tu veux
        criteria: json.criteria ?? prev?.criteria,
        results: json.results ?? prev?.results,
      }));
    } catch (_) {
      // pas bloquant
    }
  };

  const openCv = async (r) => {
    if (!data?.id_demande) return;

    try {
      setActionMsg("");
      await apiPost(`/api/demander/${data.id_demande}/${r.id_file}/viewed`);
      const url = r.cv_url || `/api/cv/${r.id_file}`;
      window.open(url, "_blank", "noopener,noreferrer");
      setActionMsg(`CV ouvert — VIEWED enregistré (file=${r.id_file}).`);

      await refreshResults();
    } catch (e) {
      setActionMsg(`Erreur VIEWED: ${e.message}`);
    }
  };

  const markInterview = async (r) => {
    if (!data?.id_demande) return;

    try {
      setActionMsg("");
      const json = await apiPost(
        `/api/demander/${data.id_demande}/${r.id_file}/interview`
      );

      const emailOk = json?.email?.ok;
      const emailStatus = json?.email?.status;

      setActionMsg(
        `INTERVIEW ${json.ok ? "OK" : "NO"} (file=${r.id_file}) — email: ${
          emailOk ? "envoyé" : "échec"
        }${emailStatus ? ` (HTTP ${emailStatus})` : ""}`
      );

      await refreshResults();
    } catch (e) {
      setActionMsg(`Erreur INTERVIEW: ${e.message}`);
    }
  };

  const badgeStyle = (status) => {
    const s = String(status || "PROPOSED").toUpperCase();
    const base = {
      padding: "2px 8px",
      borderRadius: 999,
      fontSize: 12,
      border: "1px solid #ddd",
      background: "#f7f7f7",
    };
    if (s === "VIEWED") return { ...base, background: "#eef6ff", borderColor: "#bcdcff" };
    if (s === "INTERVIEW") return { ...base, background: "#eefaf1", borderColor: "#bfe8c9" };
    return base;
  };

  const canInterview = (status) => {
    const s = String(status || "PROPOSED").toUpperCase();
    return s === "PROPOSED" || s === "VIEWED";
  };

  return (
    <div style={{ padding: 24, fontFamily: "Arial" }}>
      <h2>SmartHire — Chat Search (test)</h2>

      <div style={{ display: "flex", gap: 8, maxWidth: 900 }}>
        <input
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          style={{ flex: 1, padding: 10 }}
          placeholder="Ex: Je cherche 5 développeurs Java Spring React à Rabat"
          onKeyDown={(e) => {
            if (e.key === "Enter") runSearch();
          }}
        />
        <button
          onClick={runSearch}
          disabled={loading}
          style={{ padding: "10px 14px" }}
        >
          {loading ? "Recherche..." : "Rechercher"}
        </button>
      </div>

      {error && (
        <div style={{ marginTop: 16, color: "crimson", whiteSpace: "pre-wrap" }}>
          {error}
        </div>
      )}

      {actionMsg && (
        <div style={{ marginTop: 12, color: "#333", whiteSpace: "pre-wrap" }}>
          {actionMsg}
        </div>
      )}

      {data && (
        <div style={{ marginTop: 16 }}>
          <h3>Résultats (Demande #{data.id_demande})</h3>

          <ul style={{ paddingLeft: 18 }}>
            {data.results?.map((r) => {
              const status = r.status || "PROPOSED";
              const interviewDisabled = !canInterview(status);

              return (
                <li key={r.id_file} style={{ marginBottom: 12 }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <div style={{ flex: 1 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                        <b>
                          {r.prenom} {r.nom}
                        </b>
                        <span style={badgeStyle(status)}>
                          {String(status).toUpperCase()}
                        </span>
                      </div>
                      <div style={{ marginTop: 4, color: "#444" }}>
                        score: {Number(r.score).toFixed(3)} — {r.email}
                      </div>
                    </div>

                    <button
                      onClick={() => openCv(r)}
                      style={{ padding: "6px 10px", cursor: "pointer" }}
                      title="Ouvrir le CV + marquer VIEWED"
                    >
                      Voir CV
                    </button>

                    <button
                      onClick={() => markInterview(r)}
                      disabled={interviewDisabled}
                      style={{
                        padding: "6px 10px",
                        cursor: interviewDisabled ? "not-allowed" : "pointer",
                        opacity: interviewDisabled ? 0.6 : 1,
                      }}
                      title={
                        interviewDisabled
                          ? "Déjà en INTERVIEW"
                          : "Passer à INTERVIEW + envoyer email"
                      }
                    >
                      Je suis intéressé
                    </button>
                  </div>
                </li>
              );
            })}
          </ul>

          <details>
            <summary>JSON complet</summary>
            <pre style={{ background: "#f5f5f5", padding: 12, overflow: "auto" }}>
              {JSON.stringify(data, null, 2)}
            </pre>
          </details>
        </div>
      )}
    </div>
  );
}
