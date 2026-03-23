#!/bin/bash

# Test AJAX endpoint directly
HOST="https://beta.vanamadhuryamdaily.com"
ENDPOINT="/wp-admin/admin-ajax.php"

# Get a fresh nonce (we need to fetch the page first to get it)
echo "📍 Fetching page to extract nonce and data..."

# Use curl to get the page and extract window.vanaDrawer
curl -s "$HOST/visit/vrindavan-2026-02/" | grep -A 1 "window.vanaDrawer" | head -5 | cat

echo -e "\n\n📋 Testing AJAX endpoint..."
echo "Endpoint: $HOST$ENDPOINT"
echo ""

# The nonce will be different, but let's try to capture it from a previous response
# For now, let's make a test call to see the error
curl -s -X POST "$HOST$ENDPOINT" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=vana_get_tour_visits&tour_id=360&visit_id=359&lang=pt&_wpnonce=test" \
  | jq . 2>/dev/null || echo "Response not JSON or jq not available"
