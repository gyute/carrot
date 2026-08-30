# The PHP sandbox image. Built by CI and pulled by the runner host; never
# built on the runner. No compilers, no curl, no package manager left behind.
FROM php:8.4-cli-alpine

RUN apk add --no-cache coreutils \
    && { \
        echo 'allow_url_fopen=0'; \
        echo 'allow_url_include=0'; \
        echo 'display_errors=stderr'; \
        echo 'log_errors=0'; \
        echo 'expose_php=0'; \
    } > /usr/local/etc/php/conf.d/sandbox.ini \
    && rm -rf /usr/local/bin/docker-php-* /usr/src/php* /var/cache/apk/*

# The base image's entrypoint script was removed above; run the command as is.
ENTRYPOINT []
CMD ["php", "-v"]

USER 65534:65534
WORKDIR /work
