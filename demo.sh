#!/usr/bin/env bash
set -euo pipefail

BASE="${BASE:-http://localhost:8080}"

echo "=== GET /health ==="
curl -i "$BASE/health"
echo

echo "=== POST /auth/login ==="
TOKEN=$(curl -s -X POST "$BASE/auth/login" \
  -H 'Content-Type: application/json' \
  -d '{"email":"student@kool.ee","password":"student123"}' | jq -r .token)
echo "Token: ${TOKEN:0:20}..."
echo

echo "=== GET /loans/l-100 ==="
curl -i "$BASE/loans/l-100" -H "Authorization: Bearer $TOKEN"
echo

echo "=== POST /loans (valid) ==="
curl -i -X POST "$BASE/loans" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"userId":"u-7","itemId":"i-1","startDate":"2026-10-01","endDate":"2026-10-03"}'
echo

echo "=== POST /loans (invalid date) ==="
curl -i -X POST "$BASE/loans" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"userId":"u-7","itemId":"i-1","startDate":"2026-10-03","endDate":"2026-09-30"}'
echo

echo "=== GET /loans/l-puudub ==="
curl -i "$BASE/loans/l-puudub" -H "Authorization: Bearer $TOKEN"
echo

echo "=== GET /loan-view/l-100 ==="
curl -i "$BASE/loan-view/l-100" -H "Authorization: Bearer $TOKEN"
