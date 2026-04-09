#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

ENV_FILE="${DEPLOY_ENV_FILE:-.deploy.env}"
STATE_FILE=""
BASE_REF=""
FORCE_FULL=0
RUN_MIGRATIONS=1
DRY_RUN=0
MARK_CURRENT=0

usage() {
    cat <<'EOF'
Usage: bin/deploy.sh [options]

Options:
  --env-file PATH   Path to deploy env file (default: .deploy.env)
  --from REF        Deploy changes since git ref REF
  --full            Upload all deployable files from HEAD
  --no-migrate      Skip remote migration step
  --dry-run         Show files that would be uploaded/removed
  --mark-current    Only save current HEAD as deployed in local state
  -h, --help        Show this help

The script uploads only deployable files changed in git since the last deployed
commit (stored locally in .deploy/<name>.last_deploy). It never runs seed.sql.
Remote database changes are limited to versioned migrations from bin/migrate.php.
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --env-file)
            ENV_FILE="${2:?Missing value for --env-file}"
            shift 2
            ;;
        --from)
            BASE_REF="${2:?Missing value for --from}"
            shift 2
            ;;
        --full)
            FORCE_FULL=1
            shift
            ;;
        --no-migrate)
            RUN_MIGRATIONS=0
            shift
            ;;
        --dry-run)
            DRY_RUN=1
            shift
            ;;
        --mark-current)
            MARK_CURRENT=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

if [[ -f "$ENV_FILE" ]]; then
    # shellcheck disable=SC1090
    source "$ENV_FILE"
fi

require_env() {
    local key="$1"

    if [[ -z "${!key:-}" ]]; then
        echo "Missing required variable: $key" >&2
        exit 1
    fi
}

require_tool() {
    local tool="$1"

    if ! command -v "$tool" >/dev/null 2>&1; then
        echo "Missing required tool: $tool" >&2
        exit 1
    fi
}

require_tool git
require_tool lftp
require_tool openssl

DEPLOY_NAME="${DEPLOY_NAME:-production}"
DEPLOY_FTP_PORT="${DEPLOY_FTP_PORT:-21}"
STATE_FILE="${STATE_FILE:-.deploy/${DEPLOY_NAME}.last_deploy}"

mkdir -p "$(dirname "$STATE_FILE")"

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Worktree is not clean. Commit or stash changes before deploy." >&2
    exit 1
fi

HEAD_COMMIT="$(git rev-parse --verify HEAD)"

if [[ "$MARK_CURRENT" -eq 1 ]]; then
    printf '%s\n' "$HEAD_COMMIT" >"$STATE_FILE"
    echo "Marked ${HEAD_COMMIT} as deployed in ${STATE_FILE}"
    exit 0
fi

if [[ "$FORCE_FULL" -eq 1 && -n "$BASE_REF" ]]; then
    echo "Use either --full or --from, not both." >&2
    exit 1
fi

if [[ "$FORCE_FULL" -eq 0 && -z "$BASE_REF" && -f "$STATE_FILE" ]]; then
    BASE_REF="$(tr -d '[:space:]' <"$STATE_FILE")"
fi

if [[ "$FORCE_FULL" -eq 0 && -z "$BASE_REF" ]]; then
    echo "No previous deploy state found. Use --from <ref>, --full or --mark-current." >&2
    exit 1
fi

if [[ "$FORCE_FULL" -eq 0 ]]; then
    git rev-parse --verify "${BASE_REF}^{commit}" >/dev/null 2>&1 || {
        echo "Cannot resolve base ref: ${BASE_REF}" >&2
        exit 1
    }
fi

require_env DEPLOY_FTP_HOST
require_env DEPLOY_FTP_USER
require_env DEPLOY_FTP_PASS
require_env DEPLOY_REMOTE_DIR

if [[ "$RUN_MIGRATIONS" -eq 1 ]]; then
    require_tool curl
    require_env DEPLOY_APP_URL
fi

is_deployable_path() {
    case "$1" in
        .htaccess|index.php|composer.json|bootstrap/*|public/*|routes/*|src/*|views/*|database/migrations/*|bin/migrate.php)
            return 0
            ;;
        storage/logs/.gitkeep|storage/cache/.gitkeep)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

lftp_quote() {
    local value="$1"
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    printf '"%s"' "$value"
}

declare -a UPLOADS=()
declare -a DELETES=()

array_count() {
    local name="$1"

    if ! eval "[[ \${${name}+x} ]]"; then
        printf '0'
        return 0
    fi

    local count
    eval "count=\${#${name}[@]}"
    printf '%s' "$count"
}

add_upload() {
    local path="$1"

    is_deployable_path "$path" || return 0
    [[ -f "$path" ]] || return 0
    UPLOADS+=("$path")
}

add_delete() {
    local path="$1"

    is_deployable_path "$path" || return 0
    DELETES+=("$path")
}

if [[ "$FORCE_FULL" -eq 1 ]]; then
    while IFS= read -r path; do
        add_upload "$path"
    done < <(git ls-tree -r --name-only HEAD)
else
    while IFS=$'\t' read -r status first second; do
        [[ -n "$status" ]] || continue

        case "$status" in
            R*|C*)
                add_delete "$first"
                add_upload "$second"
                ;;
            D)
                add_delete "$first"
                ;;
            *)
                add_upload "$first"
                ;;
        esac
    done < <(git diff --name-status --find-renames "$BASE_REF" HEAD)
fi

UPLOAD_COUNT="$(array_count UPLOADS)"
DELETE_COUNT="$(array_count DELETES)"

if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "Deployable uploads:"
    if [[ "$UPLOAD_COUNT" -eq 0 ]]; then
        echo "  (none)"
    else
        printf '  %s\n' "${UPLOADS[@]}"
    fi

    echo "Deployable deletes:"
    if [[ "$DELETE_COUNT" -eq 0 ]]; then
        echo "  (none)"
    else
        printf '  %s\n' "${DELETES[@]}"
    fi

    exit 0
fi

BATCH_FILE="$(mktemp)"
RUNNER_FILE=""
RUNNER_NAME=""
RUNNER_URL=""
RUNNER_UPLOADED=0

cleanup() {
    rm -f "$BATCH_FILE"

    if [[ -n "$RUNNER_FILE" && -f "$RUNNER_FILE" ]]; then
        rm -f "$RUNNER_FILE"
    fi

    if [[ "$RUNNER_UPLOADED" -eq 1 && -n "$RUNNER_NAME" ]]; then
        lftp -u "$DEPLOY_FTP_USER","$DEPLOY_FTP_PASS" -p "$DEPLOY_FTP_PORT" "$DEPLOY_FTP_HOST" -e \
            "set cmd:fail-exit yes; set ssl:verify-certificate no; rm $(lftp_quote "${DEPLOY_REMOTE_DIR}/${RUNNER_NAME}"); bye" \
            >/dev/null 2>&1 || true
    fi
}

trap cleanup EXIT

{
    echo "set cmd:fail-exit yes"
    echo "set ssl:verify-certificate no"

    if [[ "$UPLOAD_COUNT" -gt 0 ]]; then
        for path in "${UPLOADS[@]}"; do
            remote_path="${DEPLOY_REMOTE_DIR}/${path}"
            local_dir="$(dirname "$path")"

            if [[ "$local_dir" != "." ]]; then
                echo "mkdir -p $(lftp_quote "${DEPLOY_REMOTE_DIR}/${local_dir}")"
            fi

            echo "put $(lftp_quote "${ROOT_DIR}/${path}") -o $(lftp_quote "$remote_path")"
        done
    fi

    if [[ "$DELETE_COUNT" -gt 0 ]]; then
        for path in "${DELETES[@]}"; do
            echo "rm $(lftp_quote "${DEPLOY_REMOTE_DIR}/${path}")"
        done
    fi
} >"$BATCH_FILE"

if [[ "$UPLOAD_COUNT" -gt 0 || "$DELETE_COUNT" -gt 0 ]]; then
    echo "Uploading ${UPLOAD_COUNT} changed files and removing ${DELETE_COUNT} deleted files..."
    lftp -u "$DEPLOY_FTP_USER","$DEPLOY_FTP_PASS" -p "$DEPLOY_FTP_PORT" "$DEPLOY_FTP_HOST" <"$BATCH_FILE"
else
    echo "No deployable file changes detected."
fi

if [[ "$RUN_MIGRATIONS" -eq 1 ]]; then
    RUNNER_NAME="deploy-migrate-$(openssl rand -hex 12).php"
    RUNNER_KEY="$(openssl rand -hex 24)"
    RUNNER_FILE="$(mktemp)"
    RUNNER_URL="${DEPLOY_APP_URL%/}/${RUNNER_NAME}?key=${RUNNER_KEY}"

    cat >"$RUNNER_FILE" <<PHP
<?php
declare(strict_types=1);

\$key = \$_GET['key'] ?? '';

if (!hash_equals('${RUNNER_KEY}', (string) \$key)) {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/bin/migrate.php';
PHP

    MIGRATE_BATCH="$(mktemp)"

    {
        echo "set cmd:fail-exit yes"
        echo "set ssl:verify-certificate no"
        echo "put $(lftp_quote "$RUNNER_FILE") -o $(lftp_quote "${DEPLOY_REMOTE_DIR}/${RUNNER_NAME}")"
    } >"$MIGRATE_BATCH"

    lftp -u "$DEPLOY_FTP_USER","$DEPLOY_FTP_PASS" -p "$DEPLOY_FTP_PORT" "$DEPLOY_FTP_HOST" <"$MIGRATE_BATCH"
    rm -f "$MIGRATE_BATCH"
    RUNNER_UPLOADED=1

    echo "Running remote migrations..."
    curl --fail --silent --show-error "$RUNNER_URL"
    echo

    lftp -u "$DEPLOY_FTP_USER","$DEPLOY_FTP_PASS" -p "$DEPLOY_FTP_PORT" "$DEPLOY_FTP_HOST" -e \
        "set cmd:fail-exit yes; set ssl:verify-certificate no; rm $(lftp_quote "${DEPLOY_REMOTE_DIR}/${RUNNER_NAME}"); bye"
    RUNNER_UPLOADED=0

    echo "Verifying health endpoint..."
    curl --fail --silent --show-error "${DEPLOY_APP_URL%/}/health?format=json"
    echo
fi

printf '%s\n' "$HEAD_COMMIT" >"$STATE_FILE"
echo "Deploy completed at ${HEAD_COMMIT}"
