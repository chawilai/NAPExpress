#!/usr/bin/env bash
# Spawn multiple Playwright workers for Phase 2 (profile scraping).
#
# Usage:
#   ./automation/spawn_cpp_workers.sh [num_workers] [api_url]
#
# Defaults:
#   num_workers = 4
#   api_url     = http://localhost:8000
#
# Workers run in background and log to storage/logs/cpp_worker_N.log.
# Ctrl+C to stop all workers (uses trap).

set -e

NUM_WORKERS="${1:-4}"
API_URL="${2:-http://localhost:8000}"
DELAY_MS="${DELAY_MS:-3000}"
HEADLESS="${HEADLESS:-true}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
LOG_DIR="$PROJECT_DIR/storage/logs"

mkdir -p "$LOG_DIR"

PIDS=()

cleanup() {
    echo ""
    echo "Stopping $NUM_WORKERS workers..."
    for pid in "${PIDS[@]}"; do
        kill "$pid" 2>/dev/null || true
    done
    wait 2>/dev/null || true
    echo "Stopped."
    exit 0
}

trap cleanup INT TERM

echo "Spawning $NUM_WORKERS CPP scrape workers..."
echo "  API:      $API_URL"
echo "  Delay:    ${DELAY_MS}ms"
echo "  Headless: $HEADLESS"
echo "  Logs:     $LOG_DIR/cpp_worker_N.log"
echo ""

for i in $(seq 1 "$NUM_WORKERS"); do
    WORKER_ID="w${i}"
    LOG_FILE="$LOG_DIR/cpp_worker_${i}.log"

    node "$SCRIPT_DIR/scrape_cpp_profile.cjs" \
        --worker \
        --worker-id="$WORKER_ID" \
        --api="$API_URL" \
        --headless="$HEADLESS" \
        --delay-ms="$DELAY_MS" \
        >> "$LOG_FILE" 2>&1 &

    PIDS+=($!)
    LAST_PID="${PIDS[${#PIDS[@]}-1]}"
    echo "  [$i] started worker $WORKER_ID (pid $LAST_PID) → $LOG_FILE"

    sleep 1  # stagger startup
done

echo ""
echo "All workers running. Ctrl+C to stop."
echo "Monitor progress: php artisan cpp:scrape status"
echo "Tail logs:        tail -f $LOG_DIR/cpp_worker_*.log"

wait
