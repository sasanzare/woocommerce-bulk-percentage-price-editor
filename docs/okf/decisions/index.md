# Decisions

## Adopted Constraints
- [Minimal portable implementation](../architecture/current-architecture.md): one installable PHP file, no external dependencies/assets, WooCommerce CRUD, Persian admin forms, preview-first changes, and resumable small batches.
- Each variation receives its own percentage calculation. Existing sale prices are changed only when the selected target includes them; invalid price relationships and stale preview values are skipped.
- A rollback may restore only fields still equal to the values written by its job; newer edits are preserved.
