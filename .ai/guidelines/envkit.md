<!-- envkit-guideline v1 — written by EnvKit; EnvKit replaces this file when you update it from the app. -->
# EnvKit local environment

Some developers on this project run it with EnvKit (a local web-dev stack). These notes apply only if you use EnvKit; on any other setup, ignore them.

- If you use EnvKit, the app is served at its `.test` address over HTTPS. Don't run `php artisan serve` or start another web server.
- `php` and `composer` in this project run the site's pinned PHP version through EnvKit's launchers; in a terminal opened from EnvKit, `node` and `npm` use the version from `.nvmrc`. Run them as usual; don't switch versions globally.
- Databases (MySQL/MariaDB, PostgreSQL, MongoDB), Redis and Mailpit are EnvKit services. Use the connection values in `.env`; don't start your own database, cache or mail server.
- EnvKit can run this site's queue workers and scheduler. When they are configured there, don't start `queue:work`, `queue:listen` or `schedule:work` by hand; restart them through EnvKit instead.
- EnvKit's MCP server (`envkit`) can report site details (`site_detail`), logs (`list_logs`, `tail_log`), Debug window events such as dumps, queries, mail and jobs (`debug_tail`), services (`status_live`), workers (`get_site_workers`) and databases (`databases_overview`, `db_connections`). Prefer it over guessing local state.
