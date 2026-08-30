---
paths:
  - 'app/Sandbox/**'
---

# Sandbox

## Script tools only ever run through a SandboxRunner on the sandbox queue
RunToolJob (queue `sandbox`) re-reads the source from the tool/submission and refuses to run if its sha256 differs from ToolRun.source_hash - "what runs is what was approved". Never call php/sh on user code from the web process; the web host binds NullSandboxRunner (SANDBOX_DRIVER=none) and throws if a job executes there.
DockerSandboxRunner::command() is pinned by tests/Feature/Sandbox/DockerCommandTest.php: --network none, --read-only, --user 65534:65534, --cap-drop ALL, no-new-privileges, pids/memory/cpus limits, workdir mounted :ro, `timeout -s KILL` inside. Keep every flag; ensureReady() refuses a non-rootless dockerd unless SANDBOX_REQUIRE_ROOTLESS=false.
BubblewrapSandboxRunner is the dev stand-in: the root is bound read-only so the workdir must live under the private /tmp (/tmp/work), and dash has no `ulimit -u`. Inputs are passed as a JSON file ($TOOL_INPUTS), never as argv.
