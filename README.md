# grav-plugin-google-indexing

> Grav CMS plugin — Automatically submit modified pages to the Google Indexing API using RS256 JWT and a Google Cloud service account.

## Installation

```bash
cp -r grav-plugin-google-indexing /var/www/grav/user/plugins/google-indexing
```

Then enable the plugin in Grav Admin → Plugins → Google Indexing.

## Prerequisites

- A Google Cloud project with the Indexing API enabled
- A service account with `roles/indexing.submit` permission
- The service account JSON key file stored securely (outside the web root)

## Configuration

| Parameter | Default | Description |
|-----------|---------|-------------|
| `enabled` | `false` | Enable/disable the plugin |
| `key_file` | — | Absolute path to the service account JSON key file |
| `host` | — | Your site hostname (e.g. `example.com`) |

## Hooks

| Event | Description |
|-------|-------------|
| `onAdminAfterSave` | Submits the saved page URL to Google Indexing API |
| `onMcpAfterSave` | Submits the page URL when saved via MCP server |

## Security

The service account JSON key file must be stored **outside the web root** and must never be committed to version control.

## License

MIT — Jm Rohmer / [arleo.eu](https://arleo.eu)
