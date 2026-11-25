#!/usr/bin/env bash
# Smoke tests apres deploiement : exit 1 si un service ne repond pas comme prevu
set -u
BACKEND=${BACKEND_URL:-http://localhost:8000}
FRONTEND=${FRONTEND_URL:-http://localhost:4200}
ML=${ML_URL:-http://localhost:8001}
FAIL=0

# Attend jusqu'a 150 s que le backend reponde 200 sur /api/health
wait_for() {
  for i in $(seq 1 30); do
    code=$(curl -s -o /dev/null -w '%{http_code}' "$1" || true)
    [ "$code" = "200" ] && return 0
    sleep 5
  done
  return 1
}

# check <nom> <codes attendus separes par |> <args curl...>
check() {
  name=$1; expected=$2; shift 2
  code=$(curl -s -o /dev/null -w '%{http_code}' "$@" || true)
  case "|$expected|" in
    *"|$code|"*) echo "OK   $name (HTTP $code)" ;;
    *) echo "FAIL $name (attendu $expected, obtenu $code)"; FAIL=1 ;;
  esac
}

echo "== Attente du backend =="
wait_for "$BACKEND/api/health" || { echo "FAIL backend ne repond pas"; exit 1; }

echo "== Smoke tests =="
check "Sonde sante backend (API + base de donnees)" 200 "$BACKEND/api/health"
check "Sonde sante ML service" 200 "$ML/health"
check "Frontend (page d'accueil)" 200 "$FRONTEND/"
check "Sonde sante frontend" 200 "$FRONTEND/health"
check "Route protegee sans jeton refusee" 401 -H "Accept: application/json" "$BACKEND/api/me"
check "Login sans donnees refuse proprement" "401|422" -X POST -H "Accept: application/json" "$BACKEND/api/login"

echo "== Version deployee =="
body=$(curl -s "$BACKEND/api/health")
echo "$body"
if [ -n "${EXPECTED_VERSION:-}" ]; then
  echo "$body" | grep -q "$EXPECTED_VERSION" || { echo "FAIL version attendue: $EXPECTED_VERSION"; FAIL=1; }
fi

exit $FAIL