# Project Instructions

This project builds a standalone, dependency-free WooCommerce plugin for bulk percentage price changes on simple products and individual variations.

- Keep the installable plugin to one PHP file; do not add runtime libraries, assets, build tools, or dependencies.
- Use WooCommerce CRUD APIs for product and variation changes. Never write product prices directly to the database.
- Keep price updates administrator-only, nonce-protected, previewed before application, and safe to resume in small batches.
- Do not change the live store, its products, or its configuration from this repository.
- PHP source and documentation are in English except the plugin's Persian admin interface.
- Read [HANDOFF.md](HANDOFF.md) and [docs/okf/index.md](docs/okf/index.md) before development; update HANDOFF and affected OKF concepts after meaningful changes.
- Syntax check: `php -l <plugin-file>`. Create the install ZIP from the repository root using the command in [README.md](README.md); no dependencies or build step are required.
- Sensitive areas: live WordPress admin and product prices; neither is part of local development or packaging.
