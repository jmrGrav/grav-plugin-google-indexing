## v1.0.1
### 17-04-2026
* Add default google-indexing.yaml for GPM compliance (defaults only, no secrets)
* Remove google-indexing.yaml from .gitignore — user override goes in user/config/plugins/

# Changelog

## v1.0.0
### 17-04-2026
* Initial release
* Automatic URL submission to Google Indexing API on every page save
* RS256 JWT authentication without Composer dependency
* Supports onAdminAfterSave and onMcpAfterSave hooks
* Submits 3 URL variants per page (canonical, /fr, /en)
