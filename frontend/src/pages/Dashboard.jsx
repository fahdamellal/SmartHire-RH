import { useEffect, useMemo, useState } from "react";
import { apiGet } from "../lib/api";

const card = {
  background: "rgba(17,24,39,.55)",
  border: "1px solid rgba(148,163,184,.18)",
  borderRadius: 16,
  boxShadow: "0 10px 24px rgba(0,0,0,.25)",
};

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        setLoading(true);
        const json = await apiGet("/api/dashboard/summary");
        if (alive) setData(json);
      } catch (e) {
        if (alive) setErr(e.message || "Erreur dashboard");
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => (alive = false);
  }, []);

  const statusMap = useMemo(() => {
    const map = {};
    (data?.status_distribution || []).forEach((s) => (map[s.status] = s.n));
    return map;
  }, [data]);

  const S = {
    page: {
      padding: 10, // IMPORTANT: pas de minHeight ici, App gère déjà
    },
    container: { maxWidth: 1200, margin: "0 auto" },
    top: { display: "flex", justifyContent: "space-between", gap: 12, alignItems: "baseline" },
    title: { fontSize: 20, margin: 0, color: "#fff" },
    sub: { fontSize: 12, opacity: 0.75, marginTop: 6 },
    grid: { display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 12, marginTop: 14 },
    grid2: { display: "grid", gridTemplateColumns: "1.25fr .75fr", gap: 12, marginTop: 12 },
    grid3: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginTop: 12 },
    kpi: { padding: 14, ...card },
    kpiLabel: { fontSize: 12, opacity: 0.8 },
    kpiValue: { fontSize: 26, fontWeight: 800, marginTop: 8, color: "#fff" },
    section: { padding: 14, ...card },
    sectionTitle: { fontSize: 13, opacity: 0.9, margin: 0, color: "#fff" },
    list: { marginTop: 10, display: "flex", flexDirection: "column", gap: 10 },
    row: {
      padding: 10,
      borderRadius: 12,
      border: "1px solid rgba(148,163,184,.16)",
      background: "rgba(2,6,23,.35)",
      display: "flex",
      justifyContent: "space-between",
      gap: 10,
      alignItems: "center",
    },
    badge: {
      padding: "2px 10px",
      borderRadius: 999,
      border: "1px solid rgba(148,163,184,.22)",
      background: "rgba(2,6,23,.55)",
      fontSize: 12,
      whiteSpace: "nowrap",
      color: "#fff",
    },
    mini: { fontSize: 12, opacity: 0.8 },
    table: { width: "100%", borderCollapse: "collapse", marginTop: 10, fontSize: 12 },
    th: { textAlign: "left", padding: "8px 6px", opacity: 0.8, borderBottom: "1px solid rgba(148,163,184,.16)" },
    td: { padding: "8px 6px", borderBottom: "1px solid rgba(148,163,184,.10)", verticalAlign: "top" },
    err: { color: "#FCA5A5" },
  };

  if (loading) {
    return (
      <div style={S.page}>
        <div style={S.container}>Chargement du dashboard…</div>
      </div>
    );
  }

  if (err) {
    return (
      <div style={S.page}>
        <div style={{ ...S.container, ...S.err }}>{err}</div>
      </div>
    );
  }

  const k = data?.kpis || {};
  const cq = data?.chunk_quality || {};

  return (
    <div style={S.page}>
      <div style={S.container}>
        <div style={S.top}>
          <div>
            <h2 style={S.title}>SmartHire — Dashboard</h2>
            <div style={S.sub}>Stats CVs, chunking, demandes et matching</div>
          </div>
          <div style={S.mini}>API: /api/dashboard/summary</div>
        </div>

        <div style={S.grid}>
          <div style={S.kpi}>
            <div style={S.kpiLabel}>CVs</div>
            <div style={S.kpiValue}>{k.total_cvs ?? 0}</div>
          </div>
          <div style={S.kpi}>
            <div style={S.kpiLabel}>Chunks</div>
            <div style={S.kpiValue}>{k.total_chunks ?? 0}</div>
          </div>
          <div style={S.kpi}>
            <div style={S.kpiLabel}>Demandes</div>
            <div style={S.kpiValue}>{k.total_demandes ?? 0}</div>
          </div>
          <div style={S.kpi}>
            <div style={S.kpiLabel}>Matches</div>
            <div style={S.kpiValue}>{k.total_matches ?? 0}</div>
          </div>
        </div>

        <div style={S.grid2}>
          <div style={S.section}>
            <h3 style={S.sectionTitle}>Qualité chunking</h3>
            <div style={S.list}>
              <div style={S.row}>
                <div>Moyenne chunks / CV</div>
                <div style={S.badge}>{cq.avg_chunks_per_cv ?? 0}</div>
              </div>
              <div style={S.row}>
                <div>Min chunks / CV</div>
                <div style={S.badge}>{cq.min_chunks_per_cv ?? 0}</div>
              </div>
              <div style={S.row}>
                <div>Max chunks / CV</div>
                <div style={S.badge}>{cq.max_chunks_per_cv ?? 0}</div>
              </div>
            </div>
          </div>

          <div style={S.section}>
            <h3 style={S.sectionTitle}>Matching — Statuts</h3>
            <div style={S.list}>
              <div style={S.row}>
                <div>PROPOSED</div>
                <div style={S.badge}>{statusMap.PROPOSED ?? 0}</div>
              </div>
              <div style={S.row}>
                <div>VIEWED</div>
                <div style={S.badge}>{statusMap.VIEWED ?? 0}</div>
              </div>
              <div style={S.row}>
                <div>INTERESTED</div>
                <div style={S.badge}>{statusMap.INTERESTED ?? 0}</div>
              </div>
            </div>
          </div>
        </div>

        <div style={S.grid3}>
          <div style={S.section}>
            <h3 style={S.sectionTitle}>Derniers CVs</h3>
            <table style={S.table}>
              <thead>
                <tr>
                  <th style={S.th}>Candidat</th>
                  <th style={S.th}>Email</th>
                  <th style={S.th}>Date</th>
                </tr>
              </thead>
              <tbody>
                {(data?.recent_cvs || []).map((r) => (
                  <tr key={r.id_file}>
                    <td style={{ ...S.td, color: "#fff", fontWeight: 700 }}>
                      {r.prenom} {r.nom}
                    </td>
                    <td style={S.td}>{r.email || "—"}</td>
                    <td style={S.td}>{String(r.created_at).slice(0, 19)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div style={S.section}>
            <h3 style={S.sectionTitle}>Dernières demandes</h3>
            <table style={S.table}>
              <thead>
                <tr>
                  <th style={S.th}>Entreprise</th>
                  <th style={S.th}>Texte</th>
                  <th style={S.th}>Date</th>
                </tr>
              </thead>
              <tbody>
                {(data?.recent_demandes || []).map((r) => (
                  <tr key={r.id_demande}>
                    <td style={S.td}>{r.entreprise || "—"}</td>
                    <td style={S.td}>{String(r.texte || "").slice(0, 60)}…</td>
                    <td style={S.td}>{String(r.created_at).slice(0, 19)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <div style={S.section}>
          <h3 style={S.sectionTitle}>Activité (14 jours)</h3>
          <table style={S.table}>
            <thead>
              <tr>
                <th style={S.th}>Date</th>
                <th style={S.th}>CVs ajoutés</th>
                <th style={S.th}>Demandes</th>
                <th style={S.th}>Matches</th>
              </tr>
            </thead>
            <tbody>
              {(data?.activity_14d || []).map((r) => (
                <tr key={r.date}>
                  <td style={S.td}>{r.date}</td>
                  <td style={S.td}>{r.cvs_added}</td>
                  <td style={S.td}>{r.demandes_created}</td>
                  <td style={S.td}>{r.matches_created}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

      </div>
    </div>
  );
}
