# Known issues (debug journal)

> Persistent memory across ChatGPT ↔ Cursor loops. Update when a fix fails or a hypothesis is ruled out.

## How to use

- Add a section per recurring bug or flaky area.
- List **Tried** (what did not work) so Cursor does not repeat it.
- List **Do not retry** for approaches that made things worse.

---

## Template (copy for new issues)

### [Short title]

**Symptoms:**

**Tried:**

**Likely causes:**

**Do not retry:**

---

## Geo suite — do not retry (family-wide)

- Duplicating MaxMind / visitor detection in a satellite plugin.
- City-based **page routing** inside Geo Core (`RWGC_Routing` is country-level).
- CSV or free-text country lists in admin UI (use prepopulated selects).
- Stacking defensive fallbacks instead of fixing root cause in the evaluator or hook contract.
- Patching symptoms in the wrong repo (e.g. Commerce pricing bug fixed only in Core without adapter trace).

<!-- Add plugin-specific issues below -->
