---
paths:
  - '**'
---

# General

## Comments are English; Japanese belongs in user-facing strings only
Write comments sparingly, and always in English - never Japanese or Korean, not even a single word quoted mid-sentence ("sits in 待機中", "The 所属 a tool belongs to"). Say "queued" and "department" instead; the UI label is not the term the code explains itself with. This covers PHP, TS/TSX, config files and .env.example.
Japanese is correct in anything a person reads at runtime: enum label() returns, FormRequest attributes()/messages(), flash and notification text, seeder and factory data, and the SCRIPT_TEMPLATES starter code in resources/js/pages/tools/submissions/form.tsx (that is editor content, not a source comment).
Check with: grep -rnP '^\s*(//|\*|/\*|#|\|)[^\n]*[\x{3040}-\x{30ff}\x{4e00}-\x{9fff}\x{ac00}-\x{d7af}]' app resources/js config routes database tests .env.example
