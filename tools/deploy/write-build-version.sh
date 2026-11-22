#!/bin/sh

set -eu

: "${DEPLOYPATH:?DEPLOYPATH must point at the deployed CRM directory}"

commit="$(git rev-parse --verify HEAD 2>/dev/null || true)"
if [ -z "$commit" ] && [ -d "$DEPLOYPATH/.git" ]; then
    commit="$(git -C "$DEPLOYPATH" rev-parse --verify HEAD 2>/dev/null || true)"
fi
if [ -z "$commit" ]; then
    commit="unknown"
fi

printf '<?php defined("BASEPATH") or exit("No direct script access allowed"); return "git-%s";\n' "$commit" \
    > "$DEPLOYPATH/application/config/build_version.php"
