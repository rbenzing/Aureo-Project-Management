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
status=$(curl -sS -o "$body" -w '%{http_code}' --max-time 20 "$url" || echo 000)

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
