create extension if not exists vector;

create table if not exists public.chat_sessions (
  id bigserial primary key,
  client_id text not null,
  session_id text not null,
  role text not null check (role in ('user', 'assistant', 'system')),
  content text not null,
  created_at timestamptz not null default now()
);

create index if not exists chat_sessions_client_session_idx
  on public.chat_sessions (client_id, session_id, created_at);

create table if not exists public.lead_logs (
  id bigserial primary key,
  client_id text not null,
  nombre text,
  email text,
  telefono text,
  producto_interes text,
  contexto text,
  origen text,
  estado text not null default 'OK',
  error_detalle text,
  respuesta_api jsonb,
  created_at timestamptz not null default now()
);

create index if not exists lead_logs_client_created_idx
  on public.lead_logs (client_id, created_at desc);

create table if not exists public.transcript_logs (
  id bigserial primary key,
  client_id text not null,
  file_url text,
  storage_path text,
  file_name text,
  lead_nombre text,
  lead_email text,
  message_count integer default 0,
  created_at timestamptz not null default now(),
  upload_status text,
  upload_response jsonb
);

create index if not exists transcript_logs_client_created_idx
  on public.transcript_logs (client_id, created_at desc);

create table if not exists public.documents (
  id bigserial primary key,
  content text,
  metadata jsonb,
  embedding vector(384)
);

create index if not exists documents_metadata_client_idx
  on public.documents ((metadata ->> 'client_id'));

create index if not exists documents_embedding_idx
  on public.documents using ivfflat (embedding vector_cosine_ops)
  with (lists = 100);

create or replace function public.match_documents (
  query_embedding vector(384),
  match_count int default 10,
  filter jsonb default '{}'
)
returns table (
  id bigint,
  content text,
  metadata jsonb,
  similarity float
)
language plpgsql
as $$
begin
  return query
  select
    documents.id,
    documents.content,
    documents.metadata,
    1 - (documents.embedding <=> query_embedding) as similarity
  from public.documents
  where documents.metadata @> filter
  order by documents.embedding <=> query_embedding
  limit match_count;
end;
$$;
