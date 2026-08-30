#!/bin/sh
# What a shell tool runs in: no network, read-only root, unprivileged uid.
set -eu

echo "requested lines: $(jq -r '.lines // "10"' "$TOOL_INPUTS")"
echo "user: $(id)"
echo "kernel: $(uname -srm)"
echo "writable: $(mktemp -d 2>/dev/null || echo 'none')"
