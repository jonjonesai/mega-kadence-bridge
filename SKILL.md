---
name: mega-kadence-bridge
description: Inspect and operate an authorized Kadence WordPress site through Mega Kadence Bridge. Use for Kadence theme, block, content, media, WooCommerce, plugin, cache, history, or rollback work when BRIDGE_URL, BRIDGE_USER, and BRIDGE_PASS are available; do not use for an unrelated WordPress site or without site-owner authorization.
---

# Mega Kadence Bridge

Use the bridge as a model-agnostic REST transport. It does not call or depend
on an AI provider. Codex, Claude, Cursor, local models, and ordinary scripts use
the same authenticated HTTP contract.

Before acting:

1. Confirm the target site is authorized and load `BRIDGE_URL`, `BRIDGE_USER`,
   and `BRIDGE_PASS` from a local `.env` as data. Never source the file, echo
   credentials, put them in prompts or command arguments visible in reports,
   or commit them.
2. Read [AI client guide](docs/AI-CLIENTS.md) for the transport and safety
   contract.
3. Make authenticated `GET /capabilities` the first bridge call. Treat its
   site context, installed-stack inventory, endpoint catalog, and Kadence
   doctrine as authoritative for that site.
4. For ordinary reads, use the narrowest endpoint. Before a write, read the
   current value and describe the intended change. Obtain authorization when
   the requested work does not already authorize that mutation.

For every write, send the smallest valid payload, retain the returned snapshot
identifier when present, read API state back, flush caches after a material
front-end change, and verify rendered output with `/render`. Report a change as
successful only after read-back or rendered verification. If verification
fails, stop and offer or perform `/rollback/{id}` when authorized.

Preserve existing content, settings, users, plugins, and unrelated changes.
Use Kadence palette and typography tokens rather than scattered inline values,
Kadence Blocks rather than raw layout HTML, Header/Footer Builder rather than
template forks, and `_kad_*` metadata for page-specific behavior. Branch on the
actual capabilities response instead of assuming Pro or WooCommerce features.

Do not call `/wp-eval` for routine work. Treat it as an expert-only escape hatch
that requires explicit user authorization immediately before use.

