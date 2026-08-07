#!/usr/bin/env bash
# expect.sh <url> <expected-status> [must-contain]
#
# Asserts an HTTP status and, optionally, a substring of the body. Exits
# non-zero with the actual response on mismatch so the CI log shows what
# happened rather than just that something did.
set -uo pipefail

url="$1"
expected="$2"
contains="${3:-}"

body=$(mktemp)
# No `|| echo 000` fallback: on a connection failure curl already writes 000
# via -w AND exits non-zero, so the fallback appended a second 000 and the
# failure log read "-> 000000 (expected 200)". Harmless to the verdict - six
# zeros never match a real code, so the job still failed - but it made the
# one line an operator reads to diagnose CI nonsense. `|| true` keeps `set -e`
# happy; the :- covers curl being absent entirely, where nothing is written.
status=$(curl -sS -o "$body" -w '%{http_code}' --max-time 20 "$url") || true
status=${status:-000}

if [ "$status" != "$expected" ]; then
  echo "FAIL  $url -> $status (expected $expected)"
  head -c 500 "$body"
  echo
  rm -f "$body"
  exit 1
fi

if [ -n "$contains" ] && ! grep -qF "$contains" "$body"; then
  echo "FAIL  $url returned $status but does not contain '$contains'"
  head -c 500 "$body"
  echo
  rm -f "$body"
  exit 1
fi

echo "ok    $url -> $status${contains:+ (contains '$contains')}"
rm -f "$body"
