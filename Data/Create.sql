-- 0) Extension pgvector
CREATE EXTENSION IF NOT EXISTS vector;

-- 1) demandes
CREATE TABLE demandes (
  id_demande  BIGSERIAL PRIMARY KEY,
  entreprise  TEXT,
  texte       TEXT NOT NULL,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 2) cv_files (table principale)
CREATE TABLE cv_files (
  id_file     BIGSERIAL PRIMARY KEY,
  nom         TEXT,
  prenom      TEXT,
  email       TEXT,
  phone       TEXT,

  file_path   TEXT NOT NULL,          -- chemin relatif: cv_files/123.pdf
  sha256      CHAR(64) NOT NULL,      -- anti-doublon fichier

  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Unicité anti-doublon
CREATE UNIQUE INDEX ux_cv_files_sha256 ON cv_files (sha256);

-- Si tu veux vraiment garantir un chemin unique (optionnel)
CREATE UNIQUE INDEX ux_cv_files_path ON cv_files (file_path);

-- 3) cv_chunks
CREATE TABLE cv_chunks (
  id_chunk     BIGSERIAL PRIMARY KEY,
  id_file      BIGINT NOT NULL REFERENCES cv_files(id_file) ON DELETE CASCADE,

  chunk_index  INT NOT NULL,
  chunk_text   TEXT NOT NULL,

  embedding    vector(1536) NOT NULL,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),

  CONSTRAINT uq_cv_chunks_file_index UNIQUE (id_file, chunk_index)
);

-- Index vectoriel (HNSW si disponible)
CREATE INDEX ix_cv_chunks_embedding_hnsw
ON cv_chunks USING hnsw (embedding vector_cosine_ops);

-- 4) demander (association demande ↔ cv_files)
DO $$ BEGIN
  CREATE TYPE demande_status AS ENUM ('PROPOSED','VIEWED','INTERVIEW');
EXCEPTION
  WHEN duplicate_object THEN null;
END $$;

CREATE TABLE demander (
  id_demande  BIGINT NOT NULL REFERENCES demandes(id_demande) ON DELETE CASCADE,
  id_file     BIGINT NOT NULL REFERENCES cv_files(id_file) ON DELETE CASCADE,

  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  status      demande_status NOT NULL DEFAULT 'PROPOSED',

  -- option très utile
  score       DOUBLE PRECISION,  -- ou distance

  PRIMARY KEY (id_demande, id_file)
);

CREATE INDEX ix_demander_status ON demander (status);
