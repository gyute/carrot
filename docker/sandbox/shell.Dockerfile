# The shell sandbox image: busybox sh plus coreutils (for `timeout`) and jq
# for reading the inputs file. Nothing that talks to a network.
FROM alpine:3.20

RUN apk add --no-cache coreutils jq && rm -rf /var/cache/apk/*

USER 65534:65534
WORKDIR /work
