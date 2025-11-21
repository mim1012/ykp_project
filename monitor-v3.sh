#!/bin/bash

echo "========================================"
echo "v3 Deployment Monitor"
echo "========================================"
echo "Start Time: $(date)"
echo ""

START_TIME=$(date +%s)
MAX_ITERATIONS=20
ITERATION=0

while [ $ITERATION -lt $MAX_ITERATIONS ]; do
    ITERATION=$((ITERATION + 1))
    echo ""
    echo "[$ITERATION/$MAX_ITERATIONS] Checking health status at $(date +%T)..."

    # Check health endpoint
    HEALTH_STATUS=$(curl -s https://ykperp.co.kr/health.php)
    echo "Response: $HEALTH_STATUS"

    # Check if v3 is detected
    if echo "$HEALTH_STATUS" | grep -q "v3"; then
        echo ""
        echo "========================================"
        echo "✅ v3 DETECTED! Deployment Complete!"
        echo "========================================"
        echo "Deployment completed at: $(date)"

        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))
        echo "Duration: ${DURATION} seconds"
        echo ""

        # Run API tests
        echo "========================================"
        echo "Running API Tests..."
        echo "========================================"
        echo ""

        echo "=== Test 1: Basic Store Listing (per_page=20) ==="
        curl -s "https://ykperp.co.kr/api/stores?per_page=20" | head -10
        echo ""
        echo ""

        echo "=== Test 2: Search for 천호 ==="
        curl -s "https://ykperp.co.kr/api/stores?per_page=10&search=천호" | head -10
        echo ""
        echo ""

        echo "=== Test 3: Search for 부산 ==="
        curl -s "https://ykperp.co.kr/api/stores?per_page=10&search=부산" | head -10
        echo ""
        echo ""

        echo "========================================"
        echo "Railway Deployment Logs (Last 20 lines)"
        echo "========================================"
        railway logs --deployment 2>&1 | tail -20
        echo ""

        echo "========================================"
        echo "Final Report"
        echo "========================================"
        echo "Deployment: ✅ v3 confirmed"
        echo "Health Check: ✅ $HEALTH_STATUS"
        echo ""
        echo "⚠️  NOTE: API endpoints require authentication."
        echo "   If tests show login redirects, this is expected."
        echo "   The v3 deployment itself is successful."
        echo ""

        exit 0
    fi

    if [ $ITERATION -ge $MAX_ITERATIONS ]; then
        echo ""
        echo "========================================"
        echo "⚠️  TIMEOUT: v3 not detected after $MAX_ITERATIONS attempts"
        echo "========================================"
        echo "Last status: $HEALTH_STATUS"
        echo "Duration: $(($(date +%s) - START_TIME)) seconds"
        exit 1
    fi

    echo "Waiting 30 seconds before next check..."
    sleep 30
done
