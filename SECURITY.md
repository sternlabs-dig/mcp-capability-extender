# Security

Do not include passwords, application passwords, OAuth tokens, private keys or live customer data in issues, pull requests or logs.

For a potential vulnerability, use GitHub private vulnerability reporting if enabled. If it is unavailable, open an issue requesting a private security contact **without exploit details or sensitive data**. A dedicated contact/reporting route must be established before public WordPress.org release.

This custom plugin has functional and isolated security regression tests; it has not had an independent security audit. Preview tokens do not require human approval: an authorized AI client can request both preview and save. Bulk writes are sequential, may partially complete and have no automatic rollback. Maintain tested backups and reconcile receipts before retrying interrupted writes.

Security controls apply to this extension, not to other tools or APIs available to the same account. Keep WordPress, the MCP adapter, WooCommerce and other integrations maintained.
