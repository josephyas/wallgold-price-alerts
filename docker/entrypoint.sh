#!/bin/sh
set -e

# Runtime directories must exist and be writable whether the image is used
# as built or with the source tree bind-mounted over it.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

exec "$@"
