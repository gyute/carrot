# Sandbox images

```sh
docker build -t carrot-sandbox-php:8.4   -f docker/sandbox/php.Dockerfile   docker/sandbox
docker build -t carrot-sandbox-shell:3.20 -f docker/sandbox/shell.Dockerfile docker/sandbox
```

Build these in CI and push them to the registry the runner host pulls from.
The runner never builds images itself. `SANDBOX_IMAGE_PHP` / `SANDBOX_IMAGE_SHELL`
name the tags the worker runs.
