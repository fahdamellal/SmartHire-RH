import { useEffect, useMemo, useState } from "react";
import Dashboard from "./pages/Dashboard";
import { apiPost, apiGet } from "./lib/api";

const API_BASE = (import.meta.env.VITE_API_BASE_URL || "").replace(/\/$/, "");

export default function App() {
  // ===== NAV =====
  const [view, setView] = useState("search"); // "search" | "dashboard"

  // ===== Search =====
  const [message, setMessage] = useState("Développeur React à Rabat, 3 ans d'expérience, Laravel");
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState(null);
  const [uiError, setUiError] = useState("");
  const [toast, setToast] = useState("");

  // ===== Selection =====
  const [selectedId, setSelectedId] = useState(null);
  const [profile, setProfile] = useState(null);
  const [profileLoading, setProfileLoading] = useState(false);

  // ===== LinkedIn fallback =====
  const [linkedinPost, setLinkedinPost] = useState("");
  const [liInput, setLiInput] = useState("");
  const [liBusy, setLiBusy] = useState(false);
  const hasNoResults = useMemo(() => (data ? (data.results?.length ?? 0) === 0 : false), [data]);

  const S = styles();

  const showToast = (msg) => {
    setToast(msg);
    window.clearTimeout(showToast._t);
    showToast._t = window.setTimeout(() => setToast(""), 2200);
  };

  const runSearch = async () => {
    setUiError("");
    setLoading(true);
    setData(null);
    setSelectedId(null);
    setProfile(null);
    setLinkedinPost("");
    setLiInput("");

    try {
      const json = await apiPost("/api/chat/search", { message });
      setData(json);

      const first = json?.results?.[0];
      if (first?.id_file) setSelectedId(first.id_file);

      const postText =
        json?.linkedin?.post_text ||
        json?.linkedin_post ||
        json?.linkedin_post_text ||
        "";

      if ((json?.results?.length ?? 0) === 0 && postText) {
        setLinkedinPost(postText);
      }
    } catch (e) {
      setUiError(e.message || "Erreur inconnue");
    } finally {
      setLoading(false);
    }
  };

  // fetch profile when selected changes
  useEffect(() => {
    const go = async () => {
      if (!selectedId) return;
      setProfileLoading(true);
      setProfile(null);
      setUiError("");

      try {
        const json = await apiGet(`/api/candidates/${selectedId}/profile`);
        setProfile(json);
      } catch (e) {
        setUiError(e.message || "Erreur profile");
      } finally {
        setProfileLoading(false);
      }
    };
    go();
  }, [selectedId]);

  const markViewed = async (id_file) => {
    if (!data?.id_demande) return;
    try {
      await apiPost(`/api/demander/${data.id_demande}/${id_file}/viewed`);
      showToast("VIEWED ✅");
    } catch (e) {
      showToast(`Erreur VIEWED: ${e.message}`);
    }
  };

  const markInterview = async (id_file) => {
    if (!data?.id_demande) return;
    try {
      const json = await apiPost(`/api/demander/${data.id_demande}/${id_file}/interview`);
      const ok = json?.email?.ok;
      showToast(ok ? "INTERVIEW + email ✅" : "INTERVIEW ✅ (email échec)");
      // refresh status locally (optionnel)
      setData((prev) => {
        if (!prev?.results) return prev;
        return {
          ...prev,
          results: prev.results.map((r) =>
            r.id_file === id_file ? { ...r, status: "INTERESTED" } : r
          ),
        };
      });
    } catch (e) {
      showToast(`Erreur INTERVIEW: ${e.message}`);
    }
  };

  // ✅ Ouvrir le contrat via BACKEND (pas via :5173)
  const openContractPdf = (id_file) => {
    if (!data?.id_demande) {
      showToast("Impossible: id_demande manquant");
      return;
    }
    if (!id_file) return;

    const url = `${API_BASE}/api/contracts/${data.id_demande}/${id_file}`;
    window.open(url, "_blank", "noopener,noreferrer");
  };

  const sendLinkedinRevise = async () => {
    if (!data?.id_demande) return;
    if (!linkedinPost) return;
    if (!liInput.trim()) return;

    setLiBusy(true);
    try {
      const json = await apiPost("/api/linkedin/revise", {
        id_demande: data.id_demande,
        instruction: liInput.trim(),
        current_post: linkedinPost,
      });

      const newPost = json?.post_text || json?.linkedin?.post_text || json?.post || "";
      if (newPost) {
        setLinkedinPost(newPost);
        setLiInput("");
        showToast("Post LinkedIn mis à jour ✅");
      } else {
        showToast("API revise: post_text manquant");
      }
    } catch (e) {
      showToast(`Erreur revise: ${e.message}`);
    } finally {
      setLiBusy(false);
    }
  };

  // ===== If dashboard view =====
  if (view === "dashboard") {
    return (
      <div style={S.page}>
        <div style={S.topbar}>
          <div style={S.brand}>
            <div style={S.logo}>S</div>
            <div>
              <div style={S.brandTitle}>SmartHire</div>
              <div style={S.brandSub}>Tableau de bord</div>
            </div>
          </div>

          <div style={{ display: "flex", gap: 10 }}>
            <button style={S.btnGhost} onClick={() => setView("search")}>
              ← Retour Search
            </button>
          </div>
        </div>

        <Dashboard />
      </div>
    );
  }

  // ===== Search view =====
  const results = data?.results || [];
  const selected = results.find((r) => r.id_file === selectedId) || null;

  // ✅ Top 3 INTERESTED (affichage rapide)
  const top3Interested = useMemo(() => {
    return results
      .filter((r) => String(r.status || "").toUpperCase() === "INTERESTED")
      .sort((a, b) => Number(b.score ?? 0) - Number(a.score ?? 0))
      .slice(0, 3);
  }, [results]);

  return (
    <div style={S.page}>
      {/* TOP BAR */}
      <div style={S.topbar}>
        <div style={S.brand}>
          <div style={S.logo}>S</div>
          <div>
            <div style={S.brandTitle}>SmartHire</div>
            <div style={S.brandSub}>Recherche conversationnelle de candidats</div>
          </div>
        </div>

        <div style={{ display: "flex", gap: 10 }}>
          <button style={S.btnGhost} onClick={() => setView("dashboard")}>
            Dashboard
          </button>

          <button
            style={S.btnGhost}
            onClick={() => {
              setMessage("On est l’entreprise IBM, on cherche 3 Développeur Laravel React à Rabat");
              showToast("Exemple inséré ✅");
            }}
          >
            Exemple
          </button>
        </div>
      </div>

      {/* SEARCH */}
      <div style={S.searchWrap}>
        <div style={S.searchBox}>
          <span style={S.searchIcon}>⌕</span>
          <input
            style={S.searchInput}
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            placeholder="Ex : Développeur React à Rabat, 3 ans d’expérience"
            onKeyDown={(e) => {
              if (e.key === "Enter") runSearch();
            }}
          />
          <button style={S.searchBtn} onClick={runSearch} disabled={loading}>
            {loading ? "Recherche..." : "Rechercher"}
          </button>
        </div>

        {uiError && <div style={S.error}>{uiError}</div>}
      </div>

      {/* MAIN GRID */}
      {data && (
        <div style={S.grid}>
          {/* LEFT: RESULTS */}
          <div style={S.panel}>
            <div style={S.panelHeader}>
              <div>
                <div style={S.panelTitle}>
                  Résultats {data?.id_demande ? `(Demande #${data.id_demande})` : ""}
                </div>
                <div style={S.panelSub}>{results.length} profil(s)</div>
              </div>
              <div style={S.pill}>local-dev</div>
            </div>

            {top3Interested.length > 0 && (
              <div style={{ padding: "0 12px 10px" }}>
                <div style={{ fontSize: 12, opacity: 0.85, fontWeight: 800, margin: "8px 0" }}>
                  Top 3 — INTERESTED
                </div>
                <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                  {top3Interested.map((p) => {
                    const nm = `${p.prenom ?? ""} ${p.nom ?? ""}`.trim() || `#${p.id_file}`;
                    return (
                      <button
                        key={p.id_file}
                        style={S.btnGhost}
                        onClick={() => setSelectedId(p.id_file)}
                      >
                        {nm} • {Number(p.score ?? 0).toFixed(3)}
                      </button>
                    );
                  })}
                </div>
              </div>
            )}

            <div style={S.list}>
              {results.map((r) => {
                const active = r.id_file === selectedId;
                const name = `${r.prenom ?? ""} ${r.nom ?? ""}`.trim() || `Candidat #${r.id_file}`;
                const skills = r.skills_preview || [];
                return (
                  <div
                    key={r.id_file}
                    style={active ? { ...S.card, ...S.cardActive } : S.card}
                    onClick={() => setSelectedId(r.id_file)}
                  >
                    <div style={S.row}>
                      <div style={S.avatar}>{(name[0] || "C").toUpperCase()}</div>
                      <div style={{ flex: 1 }}>
                        <div style={S.cardName}>{name}</div>
                        <div style={S.cardMeta}>
                          Score: {Number(r.score ?? 0).toFixed(3)} • {r.email || "—"}
                        </div>
                        {skills.length > 0 && (
                          <div style={S.chips}>
                            {skills.slice(0, 6).map((s, i) => (
                              <span key={i} style={S.chip}>
                                {String(s).toLowerCase()}
                              </span>
                            ))}
                          </div>
                        )}
                      </div>

                      <div style={S.statusPill}>{(r.status || "PROPOSED").toUpperCase()}</div>
                    </div>
                  </div>
                );
              })}

              {results.length === 0 && (
                <div style={S.empty}>
                  Aucun profil trouvé.
                  <div style={S.emptySub}>À droite, tu peux générer un post LinkedIn (fallback).</div>
                </div>
              )}
            </div>
          </div>

          {/* MIDDLE: PROFILE */}
          <div style={S.panel}>
            <div style={S.panelHeader}>
              <div>
                <div style={S.panelTitle}>Profil</div>
                <div style={S.panelSub}>Détails du candidat sélectionné</div>
              </div>
              {selected && <div style={S.statusPill}>{(selected.status || "PROPOSED").toUpperCase()}</div>}
            </div>

            {!selected && <div style={S.empty}>Sélectionne un candidat.</div>}

            {selected && (
              <div style={{ padding: 14 }}>
                <div style={S.profileTop}>
                  <div style={S.avatarBig}>
                    {(String(selected?.prenom || selected?.nom || "C")[0] || "C").toUpperCase()}
                  </div>

                  <div style={{ flex: 1 }}>
                    <div style={S.profileName}>
                      {`${selected?.prenom ?? ""} ${selected?.nom ?? ""}`.trim()}
                    </div>
                    <div style={S.profileMeta}>
                      Score: {Number(selected.score ?? 0).toFixed(3)} • {selected.email || "—"}
                    </div>

                    <div style={S.actions}>
                      <button
                        style={S.btnGhost}
                        onClick={async () => {
                          await markViewed(selected.id_file);
                          window.open(selected.cv_url || `/api/cv/${selected.id_file}`, "_blank", "noopener,noreferrer");
                        }}
                      >
                        Voir CV
                      </button>

                      <button style={S.btnPrimary} onClick={() => markInterview(selected.id_file)}>
                        Je suis intéressé
                      </button>

                      {/* ✅ Bouton Contrat PDF */}
                      <button
                        style={{
                          ...S.btnGhost,
                          opacity: data?.id_demande ? 1 : 0.5,
                          cursor: data?.id_demande ? "pointer" : "not-allowed",
                        }}
                        disabled={!data?.id_demande}
                        onClick={() => openContractPdf(selected.id_file)}
                        title={!data?.id_demande ? "Lance une recherche pour créer une demande (id_demande)." : "Télécharger le contrat"}
                      >
                        Contrat PDF
                      </button>
                    </div>
                  </div>
                </div>

                <div style={S.divider} />

                <div style={S.blockTitle}>Résumé</div>
                {profileLoading ? (
                  <div style={S.skeleton}>Chargement du profil…</div>
                ) : (
                  <div style={S.textBlock}>{profile?.summary?.trim() ? profile.summary : "—"}</div>
                )}

                <div style={S.divider} />

                <div style={S.blockTitle}>Expériences</div>
                {profileLoading ? (
                  <div style={S.skeleton}>Chargement…</div>
                ) : (
                  <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                    {(profile?.experiences || []).length === 0 && <div style={S.textBlock}>—</div>}
                    {(profile?.experiences || []).slice(0, 6).map((ex, idx) => (
                      <div key={idx} style={S.expCard}>
                        <div style={S.expTitleRow}>
                          <div style={S.expTitle}>{ex.title || "Expérience"}</div>
                          <div style={S.expPeriod}>{ex.period || ""}</div>
                        </div>
                        {ex.company && <div style={S.expCompany}>{ex.company}</div>}
                        {ex.description && <div style={S.expDesc}>{ex.description}</div>}
                      </div>
                    ))}
                  </div>
                )}

                <div style={S.divider} />

                <div style={S.blockTitle}>Compétences</div>
                {profileLoading ? (
                  <div style={S.skeleton}>Chargement…</div>
                ) : (
                  <div style={S.chipsWrap}>
                    {(profile?.skills || []).length === 0 && <div style={S.textBlock}>—</div>}
                    {(profile?.skills || []).slice(0, 40).map((sk, i) => (
                      <span key={i} style={S.skillChip}>
                        {String(sk).toLowerCase()}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* RIGHT: LINKEDIN */}
          <div style={S.panel}>
            <div style={S.panelHeader}>
              <div>
                <div style={S.panelTitle}>LinkedIn (fallback)</div>
                <div style={S.panelSub}>S’affiche quand il y a 0 résultats</div>
              </div>
            </div>

            <div style={{ padding: 14 }}>
              {!hasNoResults || !linkedinPost ? (
                <div style={S.textBlock}>—</div>
              ) : (
                <>
                  <pre style={S.postPre}>{linkedinPost}</pre>

                  <textarea
                    style={S.textarea}
                    value={liInput}
                    onChange={(e) => setLiInput(e.target.value)}
                    placeholder="Ex: Mets IBM, précise 5 profils, ajoute missions + CDI…"
                    rows={3}
                    onKeyDown={(e) => {
                      if ((e.ctrlKey || e.metaKey) && e.key === "Enter") sendLinkedinRevise();
                    }}
                  />

                  <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                    <button style={S.btnPrimary} onClick={sendLinkedinRevise} disabled={liBusy}>
                      {liBusy ? "Modification..." : "Modifier le post"}
                    </button>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {toast && <div style={S.toast}>{toast}</div>}
    </div>
  );
}

function styles() {
  // (Ton styles() inchangé — je le laisse identique pour ne pas casser ton design)
  return {
    page: {
      minHeight: "100vh",
      background:
        "radial-gradient(1200px 700px at 20% 0%, rgba(59,130,246,.18), transparent 60%), radial-gradient(900px 700px at 85% 10%, rgba(99,102,241,.12), transparent 55%), #05070d",
      color: "#E5E7EB",
      fontFamily: "ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial",
      padding: 18,
    },
    topbar: { display: "flex", alignItems: "center", justifyContent: "space-between", gap: 12, marginBottom: 14 },
    brand: { display: "flex", alignItems: "center", gap: 12 },
    logo: {
      width: 36,
      height: 36,
      borderRadius: 12,
      display: "grid",
      placeItems: "center",
      background: "linear-gradient(135deg, rgba(59,130,246,.9), rgba(99,102,241,.9))",
      fontWeight: 900,
    },
    brandTitle: { fontWeight: 800, fontSize: 16, color: "#fff" },
    brandSub: { fontSize: 12, opacity: 0.75 },

    searchWrap: { marginBottom: 12 },
    searchBox: {
      display: "flex",
      alignItems: "center",
      gap: 10,
      padding: 10,
      borderRadius: 16,
      border: "1px solid rgba(148,163,184,.16)",
      background: "rgba(17,24,39,.55)",
      boxShadow: "0 10px 30px rgba(0,0,0,.35)",
    },
    searchIcon: { opacity: 0.7, paddingLeft: 6 },
    searchInput: { flex: 1, border: "none", outline: "none", background: "transparent", color: "#E5E7EB", fontSize: 13, padding: 10 },
    searchBtn: {
      padding: "10px 14px",
      borderRadius: 12,
      border: "1px solid rgba(59,130,246,.35)",
      background: "rgba(37,99,235,.9)",
      color: "#fff",
      fontWeight: 700,
      cursor: "pointer",
    },

    grid: { display: "grid", gridTemplateColumns: "1.05fr 1.25fr .8fr", gap: 14, alignItems: "start" },
    panel: {
      borderRadius: 18,
      border: "1px solid rgba(148,163,184,.16)",
      background: "rgba(17,24,39,.48)",
      boxShadow: "0 10px 26px rgba(0,0,0,.30)",
      overflow: "hidden",
    },
    panelHeader: {
      padding: 14,
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      gap: 10,
      borderBottom: "1px solid rgba(148,163,184,.10)",
      background: "rgba(2,6,23,.35)",
    },
    panelTitle: { fontWeight: 800, fontSize: 14, color: "#fff" },
    panelSub: { fontSize: 12, opacity: 0.7, marginTop: 2 },
    pill: {
      padding: "4px 10px",
      borderRadius: 999,
      fontSize: 12,
      border: "1px solid rgba(148,163,184,.18)",
      background: "rgba(2,6,23,.35)",
      opacity: 0.9,
    },

    list: { padding: 12, display: "flex", flexDirection: "column", gap: 10 },
    card: {
      padding: 12,
      borderRadius: 14,
      border: "1px solid rgba(148,163,184,.12)",
      background: "rgba(2,6,23,.25)",
      cursor: "pointer",
      transition: "150ms",
    },
    cardActive: {
      border: "1px solid rgba(59,130,246,.55)",
      boxShadow: "0 0 0 1px rgba(59,130,246,.20) inset",
      background: "rgba(37,99,235,.10)",
    },
    row: { display: "flex", alignItems: "center", gap: 12 },
    avatar: {
      width: 34,
      height: 34,
      borderRadius: 12,
      display: "grid",
      placeItems: "center",
      background: "rgba(148,163,184,.10)",
      border: "1px solid rgba(148,163,184,.12)",
      fontWeight: 800,
      color: "#fff",
    },
    cardName: { fontWeight: 800, color: "#fff", fontSize: 14 },
    cardMeta: { fontSize: 12, opacity: 0.75, marginTop: 2 },

    chips: { display: "flex", flexWrap: "wrap", gap: 6, marginTop: 8 },
    chip: {
      fontSize: 11,
      padding: "4px 8px",
      borderRadius: 999,
      border: "1px solid rgba(148,163,184,.14)",
      background: "rgba(2,6,23,.30)",
      opacity: 0.95,
    },

    statusPill: {
      padding: "4px 10px",
      borderRadius: 999,
      fontSize: 11,
      border: "1px solid rgba(148,163,184,.16)",
      background: "rgba(2,6,23,.35)",
      color: "#fff",
      whiteSpace: "nowrap",
    },

    profileTop: { display: "flex", gap: 12, alignItems: "center" },
    avatarBig: {
      width: 52,
      height: 52,
      borderRadius: 18,
      display: "grid",
      placeItems: "center",
      background: "rgba(148,163,184,.10)",
      border: "1px solid rgba(148,163,184,.12)",
      fontWeight: 900,
      fontSize: 18,
      color: "#fff",
    },
    profileName: { fontWeight: 900, fontSize: 18, color: "#fff" },
    profileMeta: { fontSize: 12, opacity: 0.75, marginTop: 2 },
    actions: { display: "flex", gap: 10, marginTop: 10 },

    btnGhost: {
      padding: "9px 12px",
      borderRadius: 12,
      border: "1px solid rgba(148,163,184,.16)",
      background: "rgba(2,6,23,.20)",
      color: "#E5E7EB",
      fontWeight: 700,
      cursor: "pointer",
    },
    btnPrimary: {
      padding: "9px 12px",
      borderRadius: 12,
      border: "1px solid rgba(59,130,246,.30)",
      background: "rgba(37,99,235,.95)",
      color: "#fff",
      fontWeight: 800,
      cursor: "pointer",
    },

    divider: { height: 1, background: "rgba(148,163,184,.10)", margin: "14px 0" },
    blockTitle: { fontWeight: 900, color: "#fff", fontSize: 13, marginBottom: 8 },

    textBlock: {
      fontSize: 12,
      opacity: 0.85,
      lineHeight: 1.55,
      padding: 10,
      borderRadius: 12,
      border: "1px solid rgba(148,163,184,.10)",
      background: "rgba(2,6,23,.22)",
    },

    expCard: { padding: 10, borderRadius: 12, border: "1px solid rgba(148,163,184,.10)", background: "rgba(2,6,23,.22)" },
    expTitleRow: { display: "flex", justifyContent: "space-between", gap: 10, alignItems: "baseline" },
    expTitle: { fontWeight: 900, fontSize: 12, color: "#fff" },
    expPeriod: { fontSize: 11, opacity: 0.7 },
    expCompany: { fontSize: 12, opacity: 0.85, marginTop: 4 },
    expDesc: { fontSize: 12, opacity: 0.75, marginTop: 6, lineHeight: 1.5 },

    chipsWrap: { display: "flex", flexWrap: "wrap", gap: 8 },
    skillChip: {
      fontSize: 11,
      padding: "6px 10px",
      borderRadius: 999,
      border: "1px solid rgba(148,163,184,.14)",
      background: "rgba(2,6,23,.30)",
      color: "#E5E7EB",
    },

    postPre: {
      whiteSpace: "pre-wrap",
      padding: 12,
      borderRadius: 12,
      border: "1px solid rgba(148,163,184,.10)",
      background: "rgba(2,6,23,.22)",
      fontSize: 12,
      lineHeight: 1.55,
      maxHeight: 220,
      overflow: "auto",
      marginBottom: 10,
    },
    textarea: {
      width: "100%",
      borderRadius: 12,
      border: "1px solid rgba(148,163,184,.14)",
      background: "rgba(2,6,23,.22)",
      color: "#E5E7EB",
      padding: 10,
      outline: "none",
      resize: "vertical",
      fontSize: 12,
      lineHeight: 1.5,
    },

    empty: { padding: 14, fontSize: 13, opacity: 0.85 },
    emptySub: { marginTop: 6, fontSize: 12, opacity: 0.7 },
    skeleton: { padding: 10, borderRadius: 12, background: "rgba(148,163,184,.08)", fontSize: 12, opacity: 0.85 },

    error: {
      marginTop: 10,
      padding: 10,
      borderRadius: 12,
      border: "1px solid rgba(248,113,113,.30)",
      background: "rgba(248,113,113,.10)",
      color: "#FCA5A5",
      fontSize: 13,
      whiteSpace: "pre-wrap",
    },

    toast: {
      position: "fixed",
      right: 16,
      bottom: 16,
      padding: "10px 12px",
      borderRadius: 12,
      border: "1px solid rgba(148,163,184,.16)",
      background: "rgba(0,0,0,.75)",
      color: "#fff",
      fontSize: 13,
      maxWidth: 420,
    },
  };
}
