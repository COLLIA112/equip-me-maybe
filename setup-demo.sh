#!/usr/bin/env sh
set -eu
cd "$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
if ! command -v php >/dev/null 2>&1; then
    printf '%s\n' 'PHP 8.2 or newer is required. Install PHP or XAMPP and add PHP to PATH.' >&2
    exit 1
fi
exec php setup-demo.php "$@"
