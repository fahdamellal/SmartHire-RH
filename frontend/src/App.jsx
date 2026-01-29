import { useMemo, useState } from "react";

export default function App() {
  const [message, setMessage] = useState("Je cherche 5 plombier");
  const [loading, setLoading] = useState(false);

  // API result of /api/chat/search
  const [data, setData] = useState(null);

  // global UI messages
  const [error, setError] = useState("");
  const [actionMsg, setActionMsg] = useState("");

  // LinkedIn post state
  const [linkedinPost, setLinkedinPost] = useState("");
  const [linkedinPostId, setLinkedinPostId] = useState(null);

  // LinkedIn chat state
  const [liOpen, setLiOpen] = useState(true); // keep panel open by default when NO_RESULTS
  const [liInput, setLiInput] = useState("");
  const [liChat, setLiChat] = useState([]); // {role:'user'|'assistant'|'system', text:string}
  const [liBusy, setLiBusy] = useState(false);

  const apiPost = async (url, body) => {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: body ? JSON.stringify(body) : null,
    });

    const txt = await res.text();
    if (!res.ok) throw new Error(`HTTP ${res.status} - ${txt}`);
    try {
      return JSON.parse(txt);
    } catch {
      // if backend returns non-json
      return { raw: txt };
    }
  };

  const apiGet = async (url) => {
    const res = await fetch(url, { method: "GET", headers: { Accept: "application/json" } });
    const txt = await res.text();
    if (!res.ok) throw new Error(`HTTP ${res.status} - ${txt}`);
    try {
      return JSON.parse(txt);
    } catch {
      return { raw: txt };
    }
  };

  const refreshResults = async (id_demande) => {
    if (!id_demande) return;
    try {
      const json = await apiGet(`/api/demander/${id_demande}`);
      setData((prev) => ({
        ...prev,
        criteria: json.criteria ?? prev?.criteria,
        results: json.results ?? prev?.results,
      }));
    } catch {
      // not blocking
    }
  };

  const runSearch = async () => {
    setLoading(true);
    setError("");
    setActionMsg("");
    setData(null);

    // reset LinkedIn UI each search
    setLinkedinPost("");
    setLinkedinPostId(null);
    setLiChat([]);
    setLiInput("");
    setLiOpen(true);

    try {
      const json = await apiPost("/api/chat/search", { message });
      setData(json);

      // if NO_RESULTS, show linkedin post + initialize chat
      const postText =
        json?.linkedin?.post_text ||
        json?.linkedin_post ||
        json?.linkedin_post_text ||
        "";

      const idPost = json?.linkedin?.id_post ?? json?.linkedin?.idPost ?? null;

      if ((json?.results?.length ?? 0) === 0 && postText) {
        setLinkedinPost(postText);
        setLinkedinPostId(idPost);

        setLiChat([
          {
            role: "assistant",
            text:
              "0 résultats trouvés. Voici un post LinkedIn proposé (tu peux le modifier en envoyant des instructions).",
          },
          { role: "assistant", text: postText },
          {
            role: "assistant",
            text: "Donne une instruction (ex: “plus long”, “ajoute Rabat”, “plus pro”, “poste plus humain”, etc.).",
          },
        ]);
        setLiOpen(true);
      }
    } catch (e) {
      setError(e.message || "Erreur inconnue");
    } finally {
      setLoading(false);
    }
  };

  // ===== RESULTS actions =====
  const openCv = async (r) => {
    if (!data?.id_demande) return;
    try {
      setActionMsg("");
      await apiPost(`/api/demander/${data.id_demande}/${r.id_file}/viewed`);
      const url = r.cv_url || `/api/cv/${r.id_file}`;
      window.open(url, "_blank", "noopener,noreferrer");
      setActionMsg(`CV ouvert — VIEWED enregistré (file=${r.id_file}).`);
      await refreshResults(data.id_demande);
    } catch (e) {
      setActionMsg(`Erreur VIEWED: ${e.message}`);
    }
  };

  const markInterview = async (r) => {
    if (!data?.id_demande) return;
    try {
      setActionMsg("");
      const json = await apiPost(`/api/demander/${data.id_demande}/${r.id_file}/interview`);
      const emailOk = json?.email?.ok;
      const emailStatus = json?.email?.status;

      setActionMsg(
        `INTERVIEW ${json.ok ? "OK" : "NO"} (file=${r.id_file}) — email: ${
          emailOk ? "envoyé" : "échec"
        }${emailStatus ? ` (HTTP ${emailStatus})` : ""}`
      );
      await refreshResults(data.id_demande);
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

  // ===== LinkedIn chat =====
  const hasNoResults = useMemo(() => (data ? (data.results?.length ?? 0) === 0 : false), [data]);

  const sendLiInstruction = async () => {
    if (!liInput.trim()) return;
    if (!data?.id_demande) return;

    const instruction = liInput.trim();
    setLiInput("");

    // keep the chat open and append the user's message
    setLiChat((prev) => [...prev, { role: "user", text: instruction }]);
    setLiBusy(true);
    setActionMsg("");

    try {
      // ✅ Route simple by demande (ton route:list montre POST api/linkedin/revise)
      const json = await apiPost("/api/linkedin/revise", {
        id_demande: data.id_demande,
        instruction,
        current_post: linkedinPost,
      });

      const newPost =
        json?.post_text ||
        json?.linkedin?.post_text ||
        json?.post ||
        "";

      if (!newPost) {
        // keep chat open, just warn
        setLiChat((prev) => [
          ...prev,
          {
            role: "assistant",
            text:
              "Je n’ai pas reçu de nouveau texte depuis l’API. Vérifie la réponse backend (champ post_text).",
          },
        ]);
      } else {
        setLinkedinPost(newPost);

        // append assistant answer (updated post)
        setLiChat((prev) => [
          ...prev,
          { role: "assistant", text: "✅ Post mis à jour :" },
          { role: "assistant", text: newPost },
          {
            role: "assistant",
            text:
              "Tu veux changer quoi d’autre ? (ex: ton plus pro, ajouter ville/contrat, missions plus précises, etc.)",
          },
        ]);
      }
    } catch (e) {
      setLiChat((prev) => [
        ...prev,
        {
          role: "assistant",
          text: `❌ Erreur côté API /api/linkedin/revise. Backend dit: ${e.message}`,
        },
        {
          role: "assistant",
          text: "On peut réessayer avec une instruction plus précise, mais il faut d’abord corriger l’erreur backend.",
        },
      ]);
    } finally {
      setLiBusy(false);
    }
  };

  const endLiChat = () => {
    // user can end when THEY want
    setLiOpen(false);
    setLiChat((prev) => [...prev, { role: "system", text: "Conversation terminée." }]);
  };

  // UI helpers
  const chatBubbleStyle = (role) => {
    const isUser = role === "user";
    const isSystem = role === "system";
    return {
      alignSelf: isSystem ? "center" : isUser ? "flex-end" : "flex-start",
      maxWidth: "92%",
      whiteSpace: "pre-wrap",
      padding: "10px 12px",
      borderRadius: 12,
      fontSize: 13,
      lineHeight: 1.45,
      border: "1px solid #2b2b2b",
      background: isSystem ? "#0b1020" : isUser ? "#111827" : "#0f172a",
      color: "#f9fafb",
      opacity: isSystem ? 0.85 : 1,
    };
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
        <button onClick={runSearch} disabled={loading} style={{ padding: "10px 14px" }}>
          {loading ? "Recherche..." : "Rechercher"}
        </button>
      </div>

      {error && (
        <div style={{ marginTop: 16, color: "crimson", whiteSpace: "pre-wrap" }}>{error}</div>
      )}

      {actionMsg && (
        <div style={{ marginTop: 12, color: "#333", whiteSpace: "pre-wrap" }}>{actionMsg}</div>
      )}

      {data && (
        <div style={{ marginTop: 16 }}>
          <h3>Résultats (Demande #{data.id_demande})</h3>

          {/* ===== If results exist ===== */}
          {(data.results?.length ?? 0) > 0 && (
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
                          <span style={badgeStyle(status)}>{String(status).toUpperCase()}</span>
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
          )}

          {/* ===== If 0 results => LinkedIn Chat ===== */}
          {hasNoResults && linkedinPost && (
            <div style={{ marginTop: 16, border: "1px solid #ddd", borderRadius: 10, overflow: "hidden" }}>
              <div style={{ padding: 12, background: "#0b1020", color: "#fff" }}>
                <b>0 résultats — Chat LinkedIn (reste ouvert)</b>
                <div style={{ fontSize: 12, opacity: 0.85, marginTop: 4 }}>
                  Le post affiché ci-dessous est mis à jour à chaque instruction.
                </div>
              </div>

              {liOpen ? (
                <div style={{ padding: 12, background: "#0f0f10" }}>
                  <div
                    style={{
                      display: "flex",
                      flexDirection: "column",
                      gap: 10,
                      maxHeight: 360,
                      overflow: "auto",
                      paddingRight: 6,
                    }}
                  >
                    {liChat.map((m, idx) => (
                      <div key={idx} style={chatBubbleStyle(m.role)}>
                        {m.text}
                      </div>
                    ))}
                  </div>

                  <div style={{ marginTop: 12 }}>
                    <div style={{ fontSize: 13, color: "#bdbdbd", marginBottom: 6 }}>
                      Donne une instruction (ex: “plus long”, “ajoute Rabat”, “mentionne CDI”, “ton plus humain”…)
                    </div>

                    <textarea
                      value={liInput}
                      onChange={(e) => setLiInput(e.target.value)}
                      rows={3}
                      style={{ width: "100%", padding: 10, borderRadius: 8 }}
                      placeholder="Ex: Mets 'Capgemini' comme entreprise, précise qu'on cherche 5 developpeurs à Rabat, ajoute missions concrètes + type de contrat."
                      onKeyDown={(e) => {
                        if ((e.ctrlKey || e.metaKey) && e.key === "Enter") sendLiInstruction();
                      }}
                    />

                    <div style={{ display: "flex", gap: 8, marginTop: 10 }}>
                      <button
                        onClick={sendLiInstruction}
                        disabled={!liInput.trim() || liBusy}
                        style={{
                          padding: "8px 12px",
                          cursor: liInput.trim() && !liBusy ? "pointer" : "not-allowed",
                          opacity: liInput.trim() && !liBusy ? 1 : 0.6,
                        }}
                      >
                        {liBusy ? "Modification..." : "Modifier le post"}
                      </button>

                      <button
                        onClick={endLiChat}
                        style={{ padding: "8px 12px", cursor: "pointer" }}
                        title="Fermer le chat quand TU veux"
                      >
                        Terminer
                      </button>
                    </div>

                    <div style={{ marginTop: 10, fontSize: 12, color: "#bdbdbd" }}>
                      Astuce : Ctrl+Enter (ou Cmd+Enter) pour envoyer plus vite.
                    </div>

                    <details style={{ marginTop: 10 }}>
                      <summary style={{ cursor: "pointer", color: "#e5e7eb" }}>
                        Post LinkedIn (texte courant)
                      </summary>
                      <pre style={{ background: "#111827", color: "#F9FAFB", padding: 12, borderRadius: 8, overflow: "auto" }}>
                        {linkedinPost}
                      </pre>
                    </details>
                  </div>
                </div>
              ) : (
                <div style={{ padding: 12 }}>
                  <button
                    onClick={() => setLiOpen(true)}
                    style={{ padding: "8px 12px", cursor: "pointer" }}
                  >
                    Rouvrir le chat LinkedIn
                  </button>
                </div>
              )}
            </div>
          )}

          <details style={{ marginTop: 16 }}>
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
