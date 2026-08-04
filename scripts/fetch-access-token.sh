#!/usr/bin/env bash
set -euo pipefail

usage() {
  echo "Usage: $0 <email> <password> [--signup] [--api-url URL] [--anon-key KEY]" >&2
  exit 2
}

EMAIL="${1:-}"
PASSWORD="${2:-}"
[[ -n "$EMAIL" && -n "$PASSWORD" ]] || usage
shift 2

SIGNUP=0
API_URL="${SUPABASE_API_URL:-}"
ANON_KEY="${SUPABASE_ANON_KEY:-}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --signup) SIGNUP=1; shift ;;
    --api-url) API_URL="${2:-}"; shift 2 ;;
    --anon-key) ANON_KEY="${2:-}"; shift 2 ;;
    *) usage ;;
  esac
done

load_supabase_env() {
  if ! command -v supabase >/dev/null 2>&1; then
    return 1
  fi
  eval "$(supabase status -o env 2>/dev/null | python3 -c '
import sys, json, shlex
raw = sys.stdin.read()
data = {}
s, e = raw.find("{"), raw.rfind("}")
if s >= 0 and e > s:
    try:
        data = json.loads(raw[s:e+1])
    except json.JSONDecodeError:
        data = {}
if not data:
    for line in raw.splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        if len(v) >= 2 and v[0] == v[-1] and v[0] in "\"'\''":
            v = v[1:-1]
        data[k] = v
for key in ("API_URL", "ANON_KEY"):
    if data.get(key):
        print(f"{key}={shlex.quote(str(data[key]))}")
')"
}

if [[ -z "$API_URL" || -z "$ANON_KEY" ]]; then
  load_supabase_env || true
fi

if [[ -z "${API_URL:-}" || -z "${ANON_KEY:-}" ]]; then
  echo "Missing API_URL or ANON_KEY. Run from a supabase project with \`supabase start\`." >&2
  exit 1
fi

BODY="$(python3 -c 'import json,sys; print(json.dumps({"email":sys.argv[1],"password":sys.argv[2]}))' "$EMAIL" "$PASSWORD")"

if [[ "$SIGNUP" -eq 1 ]]; then
  curl -sS "${API_URL}/auth/v1/signup" \
    -H "apikey: ${ANON_KEY}" \
    -H "Content-Type: application/json" \
    -d "$BODY" >/dev/null || true
fi

RESP="$(curl -sS "${API_URL}/auth/v1/token?grant_type=password" \
  -H "apikey: ${ANON_KEY}" \
  -H "Content-Type: application/json" \
  -d "$BODY")"

TOKEN="$(printf "%s" "$RESP" | python3 -c "import json,sys; print(json.load(sys.stdin).get(\"access_token\") or \"\")")"

if [[ -z "$TOKEN" ]]; then
  echo "Failed to obtain access_token. Response:" >&2
  echo "$RESP" >&2
  exit 1
fi

# Ensure profiles.external_id = JWT sub (needed when handle_new_user was missing
# at signup, or for users created before that trigger).
SUB="$(printf "%s" "$TOKEN" | python3 -c '
import base64, json, sys
token = sys.stdin.read().strip()
seg = token.split(".")[1]
pad = "=" * (-len(seg) % 4)
payload = json.loads(base64.urlsafe_b64decode(seg + pad))
print(payload.get("sub") or "")
')"

if [[ -z "$SUB" ]]; then
  echo "Warning: JWT has no sub; skipped profiles ensure." >&2
elif ! command -v supabase >/dev/null 2>&1; then
  echo "Warning: supabase CLI missing; skipped profiles ensure (auth_profile_not_found possible)." >&2
else
  # Quote sub for SQL safely (uuid chars only expected).
  if [[ ! "$SUB" =~ ^[0-9a-fA-F-]{36}$ ]]; then
    echo "Warning: unexpected sub format; skipped profiles ensure." >&2
  else
    INSERT_OUT="$(supabase db query --local "insert into public.profiles (id, external_id)
select public.generate_ulid(), '${SUB}'
where not exists (
  select 1 from public.profiles p where p.external_id = '${SUB}'
);" 2>&1)" || true
    QUERY_OUT="$(supabase db query --local "select id from public.profiles where external_id = '${SUB}';" 2>&1)" || true
    PROFILE_ID="$(printf "%s" "$QUERY_OUT" | python3 -c '
import json, sys, re
raw = sys.stdin.read()
m = re.search(r"\{.*\}", raw, re.S)
if m:
    try:
        data = json.loads(m.group(0))
        rows = data.get("rows") or []
        if rows and "id" in rows[0]:
            print(rows[0]["id"])
            raise SystemExit
    except Exception:
        pass
print("")
')"
    if [[ -n "$PROFILE_ID" ]]; then
      echo "profile ok id=${PROFILE_ID}" >&2
    else
      echo "Warning: could not ensure profiles row. Apply migrations: supabase migration up --local" >&2
      echo "$INSERT_OUT" >&2
      echo "$QUERY_OUT" >&2
    fi
  fi
fi

if command -v pbcopy >/dev/null 2>&1; then
  printf "%s" "$TOKEN" | pbcopy
  echo "access_token copied to clipboard" >&2
fi

printf "%s\n" "$TOKEN"
