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
    return () => {
      alive = false;
    };
  }, []);

  const statusMap = useMemo(() => {
    const map = {};
    (data?.status_distribution || []).forEach((s) => (map[s.status] = s.n));
    return map;
  }, [data]);

  const S = {
    page: {
      minHeight: "100vh",
      background: "linear-gradient(180deg,#070A12,#0B1220 60%, #070A12)",
      color: "#E5E7EB",
      fontFamily: "ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial",
      padding: 20,
    },
    container: { maxWidth: 1200, margin: "0 auto" },
    top: { display: "flex", justifyContent: "space-between", gap: 12, alignItems: "baseline" },
    title: { fontSize: 20, margin: 0, color: "#fff", fontWeight: 900 },
    sub: { fontSize: 12, opacity: 0.75, marginTop: 6 },
    grid: { display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 12, marginTop: 14 },
    grid2: { display: "grid", gridTemplateColumns: "1.25fr .75fr", gap: 12, marginTop: 12 },
    grid3: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginTop: 12 },
    kpi: { padding: 14, ...card },
    kpiLabel: { fontSize: 12, opacity: 0.8 },
    kpiValue: { fontSize: 26, fontWeight: 900, marginTop: 8, color: "#fff" },
    section: { padding: 14, ...card },
    sectionTitle: { fontSize: 13, opacity: 0.9, margin: 0, fontWeight: 800 ,alignItems: "center" },
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
      fontWeight: 800,
    },
    mini: { fontSize: 12, opacity: 0.8 },
    table: {
      width: "100%",
      borderCollapse: "collapse",
      marginTop: 10,
      fontSize: 12,
    },
    th: { textAlign: "left", padding: "8px 6px", opacity: 0.8, borderBottom: "1px solid rgba(148,163,184,.16)" },
    td: { padding: "8px 6px", borderBottom: "1px solid rgba(148,163,184,.10)", verticalAlign: "top", opacity: 0.9 },
    tdStrong: { padding: "8px 6px", borderBottom: "1px solid rgba(148,163,184,.10)", verticalAlign: "top", color: "#fff", fontWeight: 800 },
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
        <div style={{ ...S.container, color: "#FCA5A5" }}>{err}</div>
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
            <div style={S.sub}></div>
          </div>
          <div style={S.mini}></div>
        </div>

        {/* KPIs */}
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

        {/* Chunk quality + Status distribution */}
        <div style={S.grid2}>
          <div style={S.section}>
            <h3 style={S.sectionTitle}>Qualité chunking</h3>
            <div style={S.list}>

              <div style={S.row}>
                <div>Max chunks </div>
                <div style={S.badge}>{cq.max_chunks_per_cv ?? 0}</div>
              </div>
              <div style={S.row}>
                <div>Moyenne chunks </div>
                <div style={S.badge}>{cq.avg_chunks_per_cv ?? 0}</div>
              </div>
              <div style={S.row}>
                <div>Min chunks </div>
                <div style={S.badge}>{cq.min_chunks_per_cv ?? 0}</div>
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

        {/* Recent CVs + Recent demandes */}
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
                    <td style={S.tdStrong}>{(r.prenom || "—")} {(r.nom || "")}</td>
                    <td style={S.td}>{r.email || "—"}</td>
                    <td style={S.td}>{String(r.created_at).slice(0, 19)}</td>
                  </tr>
                ))}
                {(data?.recent_cvs || []).length === 0 && (
                  <tr>
                    <td style={S.td} colSpan={3}>—</td>
                  </tr>
                )}
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
                    <td style={S.td}>{String(r.texte || "").slice(0, 60)}{String(r.texte || "").length > 60 ? "…" : ""}</td>
                    <td style={S.td}>{String(r.created_at).slice(0, 19)}</td>
                  </tr>
                ))}
                {(data?.recent_demandes || []).length === 0 && (
                  <tr>
                    <td style={S.td} colSpan={3}>—</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Top skills */}
        <div style={S.grid3}>
          <div style={S.section}>
            <h3 style={S.sectionTitle} >Top Skills</h3>
            <div style={{ ...S.list, gap: 8 }}>
              {(data?.top_skills || []).map((s) => (
                <div key={s.skill} style={S.row}>
                  <div>{s.skill}</div>
                  <div style={S.badge}>{s.n}</div>
                </div>
              ))}
              {(!data?.top_skills || data.top_skills.length === 0) && (
                <div style={S.mini}>
                  Aucun skill détecté (vérifie que cv_files.skills est un JSON array).
                </div>
              )}
            </div>
          </div>

          {/* Top 3 INTERESTED */}
<div style={S.section}>
  <h3 style={S.sectionTitle}>Top 3 profils — INTERESTED</h3>

  {(data?.top_interested || []).length === 0 ? (
    <div style={S.mini}>Aucun profil en INTERESTED pour le moment.</div>
  ) : (
    <table style={S.table}>
      <thead>
        <tr>
          <th style={S.th}>Candidat</th>
          <th style={S.th}>Email</th>
          <th style={S.th}>Score</th>
          <th style={S.th}>Demande</th>
          <th style={S.th}>Entreprise</th>
        </tr>
      </thead>
      <tbody>
        {data.top_interested.map((p) => (
          <tr key={`${p.id_demande}-${p.id_file}`}>
            <td style={{ ...S.td, color: "#fff", fontWeight: 800 }}>
              {(p.prenom || "—")} {(p.nom || "")}
            </td>
            <td style={S.td}>{p.email || "—"}</td>
            <td style={S.td}>{p.score !== null ? Number(p.score).toFixed(3) : "—"}</td>
            <td style={S.td}>#{p.id_demande}</td>
            <td style={S.td}>{p.entreprise || "—"}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )}
</div>
        </div>

        {/* activity */}
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
              {(data?.activity_14d || []).length === 0 && (
                <tr>
                  <td style={S.td} colSpan={4}>—</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

      </div>
    </div>
  );
}
