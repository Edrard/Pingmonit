#!/bin/bash

set -euo pipefail


SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

if [[ ! -f "run.php" ]]; then
    echo "ERROR: run.php not found. Please run update.sh from the PingMonit repository root." >&2
    exit 1
fi

user=$(stat -c '%U' run.php)
group=$(stat -c '%G' run.php)

git_version=$(git ls-remote https://github.com/Edrard/Pingmonit.git HEAD | cut -f1)
local_version=$(git rev-parse HEAD)
if [[ ${local_version} != ${git_version} ]]; then
    if ! git diff --quiet || ! git diff --cached --quiet; then
        echo "ERROR: You have local changes. Commit/stash them before running update.sh." >&2
        exit 1
    fi

    git fetch --all
    git checkout main
    git pull --ff-only origin main
fi

export COMPOSER_ALLOW_SUPERUSER=1
composer self-update --2
yes | composer update --no-dev
export COMPOSER_ALLOW_SUPERUSER=0


if [[ "${EUID}" -eq 0 ]]; then
    chown -R "${user}:${group}" .
fi

if [[ -f "test_json.sh" ]]; then
    chmod 755 test_json.sh
fi
chmod 755 update.sh
