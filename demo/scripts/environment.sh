#!/bin/sh
# Shows what a shell tool actually runs in: no network, a read-only root and
# an unprivileged uid. `jq` is in the image, so the inputs file is readable.
set -eu

echo "requested lines: $(jq -r '.lines // "10"' "$TOOL_INPUTS")"
echo "user: $(id)"
echo "kernel: $(uname -srm)"
echo "writable: $(mktemp -d 2>/dev/null || echo 'none')"
