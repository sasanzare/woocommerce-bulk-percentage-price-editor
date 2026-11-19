# Project Handoff

## Last Updated
2026-11-19 (Asia/Tehran)

## Project Summary
A portable, dependency-free WooCommerce plugin for previewed percentage adjustments to simple products and individual variations.

## Current Objective
Maintain the lightweight, portable WooCommerce bulk-price plugin and its installation documentation. Live installation remains a separate action.

## Current Phase or Milestone
Plugin implementation, single-file packaging, README, and ZIP ignore rule are complete. PHP syntax validation passed. No behavioral testing or live installation has been performed.

## Repository State
- Repository path: `D:\All projects\دامین`
- Current branch: `main`
- Base or starting commit: `26b2a34c8cffb9b7ce1a7c894f9fe581ae63e3f8`
- Current HEAD: `26b2a34c8cffb9b7ce1a7c894f9fe581ae63e3f8`
- Working tree status: `README.md` modified; `.gitignore`, `AGENTS.md`, `HANDOFF.md`, `docs/`, and `woocommerce-bulk-percentage-price-editor/` untracked; the generated ZIP is ignored.
- Staged changes: None.
- Unstaged changes: `README.md`.
- Untracked files: `.gitignore`, `AGENTS.md`, `HANDOFF.md`, `docs/`, and `woocommerce-bulk-percentage-price-editor/`.

## Completed Work
- Created the one-file plugin at `woocommerce-bulk-percentage-price-editor/woocommerce-bulk-percentage-price-editor.php`.
- Added Persian WooCommerce admin controls for published simple/variable products, category filtering, regular/sale/both targets, percentage increase/decrease, preview, and resumable batches of 25 items.
- Each variation is calculated individually. Preview jobs are user-bound and expire after 24 hours. Stale prices and price relationships that would become invalid are skipped. A guarded rollback preserves later price edits.
- Added the installable ZIP at `woocommerce-bulk-percentage-price-editor.zip`; its archive contains only the plugin PHP file.
- Expanded `README.md` with requirements, installation, workflow, pricing behavior, limits, recovery, and development instructions.
- Added a root `.gitignore` rule for `woocommerce-bulk-percentage-price-editor.zip`; verified that the ZIP is ignored and is not tracked.
- Initialized and updated the project OKF 0.2 knowledge bundle.

## Work in Progress
None in the local implementation. The plugin has not been installed on the live site.

## Remaining Work
- If the user wants installation now, install the prepared ZIP through the authenticated WordPress admin at `https://zhuboutiq.com/` and verify that it is active. The browser action needs action-time confirmation because it installs custom software on the live site.
- Behavioral verification in a disposable WooCommerce environment remains undone; no product prices were changed during this task.
- The user can review and stage the source/documentation, then commit and push. No files have been staged, committed, or pushed by the assistant.
- No further Notion work is pending for this task; a second local queue event was appended in `PENDING` state. Both events remain unsynced.
- Review or update the project-specific mistakes log only if access to `D:\All projects\Mistakes\دامین\mistakes.md` becomes available.

## Exact Next Steps
1. Review the changes and commit with the user's preferred message; `.gitignore` excludes the local ZIP.
2. If requested, obtain action-time confirmation to install the self-built plugin ZIP on `zhuboutiq.com`, then verify its WooCommerce submenu. Do not apply a price change without a separate explicit request.
3. Do not sync the local queue unless the user explicitly requests `SYNC_NOTION`.

## Files Created
- `AGENTS.md`
- `HANDOFF.md`
- `.gitignore`
- `woocommerce-bulk-percentage-price-editor/woocommerce-bulk-percentage-price-editor.php`
- `woocommerce-bulk-percentage-price-editor.zip`
- `docs/okf/index.md`
- `docs/okf/log.md`
- `docs/okf/project-overview.md`
- `docs/okf/architecture/index.md`
- `docs/okf/architecture/current-architecture.md`
- `docs/okf/decisions/index.md`

## Files Modified
- `README.md`
- `AGENTS.md`
- `HANDOFF.md`
- `docs/okf/project-overview.md`
- `docs/okf/log.md`
- `docs/okf/architecture/current-architecture.md`
- `docs/okf/decisions/index.md`

## Important Architecture and Design Decisions
- The installable plugin is one PHP file, with no external libraries, assets, or build step.
- Price reads and writes use WooCommerce product objects and CRUD methods. Changed variable-product parents are synchronized and their product transients are cleared.
- Scope is published simple/variable products by type and category, capped at 5,000 parent products and 10,000 price items per preview.
- Prices are rounded using WooCommerce's configured decimal precision. Sale prices are adjusted only when selected. Unchanged dependent price fields are guarded against concurrent edits.
- Job snapshots live in user-bound WordPress transients for up to 24 hours. Each request processes at most 25 items. Rollback only restores fields that still match the job's saved values.
- Product `save()` uses normal WooCommerce hooks. Existing integration plugins may observe those saves; the Basalam integration's live behavior was not investigated.

## Commands Executed
- `git -c core.fsmonitor=false status --short --branch`, branch/HEAD discovery, diff summaries, and tracked-file inspection; confirmed no staged changes and the README is the only modified tracked file.
- `git -c core.fsmonitor=false check-ignore -v -- woocommerce-bulk-percentage-price-editor.zip` — confirmed the root `.gitignore` rule excludes the generated ZIP.
- `& 'D:\xampp\php\php.exe' -l 'woocommerce-bulk-percentage-price-editor\woocommerce-bulk-percentage-price-editor.php'` — run after the final source edit.
- `Compress-Archive` and ZIP inspection — confirmed the archive contains only `woocommerce-bulk-percentage-price-editor.php`.
- Read-only code review by Root. No behavioral tests were run.
- External review attempts: Cline failed with a sandbox filesystem error, then token refresh returned HTTP 403; Qoder Qwen3.8-Flash reached an explicit credit-usage limit after its sandbox retry; Copilot failed package extraction in the sandbox, then its policy denied access after escalation. No worker edited files.
- Appended one locked `PENDING` event for this documentation task; two local events are now pending. No Notion API or remote workspace was accessed.

## Validation and Test Results
- PHP syntax check: passed, `No syntax errors detected`.
- ZIP content inspection: passed; exactly one PHP file is present.
- Git ignore check: passed; the generated ZIP is ignored and no longer appears in `git status`.
- Behavioral tests: not run.
- Live WordPress installation or product-price update: not performed.
- Project and OKF documentation: manually reviewed after updates; internal links point to existing files.
- Local Notion queue: append command returned `EVENT_APPENDED`; two events remain pending sync.

## Known Issues and Blockers
- The project-specific mistakes log at `D:\All projects\Mistakes\دامین\mistakes.md` is outside the writable workspace and could not be reviewed or changed.
- Two shared-queue events are local and pending; remote Notion synchronization remains prohibited without an explicit user command.
- Product save hooks may interact with installed synchronization plugins. This has not been checked against the live store.
- No disposable WooCommerce runtime was used for behavior tests.

## Risks and Assumptions
- The plugin deliberately changes only published products and never changes prices on activation.
- One category/type preview may be rejected when it exceeds the documented caps; the administrator must narrow the filters.
- A change to a price after preview causes that item to be skipped. Rollback similarly skips values changed after the job.
- The live site admin was observed to show WooCommerce 11.0.1 in its footer during the earlier read-only review; compatibility was not exercised against that installation.

## Database or Migration State
No database access, migrations, or product-price changes were performed.

## Configuration and Environment Notes
- Runtime requirement: WordPress and active WooCommerce; plugin header requires PHP 7.4 or newer and WordPress 6.2 or newer.
- No third-party runtime dependency or machine-specific configuration is embedded.

## Uncommitted or Partially Applied Changes
The repository is on `main` at `26b2a34c8cffb9b7ce1a7c894f9fe581ae63e3f8`. No changes are staged or committed. `README.md` is modified; project files and documentation remain untracked; the generated ZIP is ignored. No partial live change exists.

## Recovery or Rollback Notes
The plugin includes a 24-hour rollback action for completed jobs. It restores only price fields that still equal the job's applied values; later edits are preserved and counted as conflicts. Deactivating the plugin does not delete product data.

## Related Documentation
- [User README](README.md)
- [Project instructions](AGENTS.md)
- [OKF bundle index](docs/okf/index.md)
- [Project overview](docs/okf/project-overview.md)
- [Current architecture](docs/okf/architecture/current-architecture.md)
- [Decisions](docs/okf/decisions/index.md)

## Notes for the Next Agent
Use the local ZIP for the proposed site installation. Confirm the plugin becomes active in WordPress, then stop before applying any bulk price operation unless the user explicitly specifies the target scope and percentage.
