MiniWiki (Markdown Wiki for PHP4/Cafe24)

Overview
- Lightweight Markdown-only wiki engine for legacy PHP 4 hosting (e.g., Cafe24).
- No DB, no external libraries; pure PHP 4–compatible code.
- FrontPage and SyntaxGuide are regular pages under `data/pages/` (no special handling).
- Loginless mode by default; secure via IP allowlist (see below).

🔐 Security Modes
- Loginless (default): No application login. Protect access using server IP allowlist (`.htaccess`).
- Login-enabled (optional): Session-based login system.
  - Default admin: `admin` / `passw0rd`
  - To enable login, set `WIKI_REQUIRE_LOGIN` to `true` in `public/wiki.php`.
  - Session timeout: 3 hours

Account & Users (UI)
- Available only when login is enabled.
- Account: `wiki.php?a=account` (self password change)
- Users (admin only): `wiki.php?a=users` (list/add/delete/reset)
- You may still edit `data/users.json` directly if needed.

Engine
- File-based wiki engine (PHP4 compatible).
- Routes: front, view, edit, save, all pages, search, history, rename, delete, new, login, logout, account, users.
- Storage:
  - Pages: `data/pages/<Title>.md` (Title is `rawurlencode`d)
  - History: `data/history/<Title>/<STAMP>.md`
- Edit/Delete protection: hardcoded password is a single backtick character: `

Rendering
- Markdown renderer with tables, fenced code, lists, headings, images, emphasis.
- Extensions: task list items (`- [ ]`, `- [x]`), footnotes (`[^id]` with definitions), link titles in `[text](url "title")`.
- Images: `![alt](url)` supports:
  - External `http/https` URLs
  - Relative paths under `data/` — served via `wiki.php?a=asset&path=...`
  - Bare filenames default to `data/images/` (e.g., `![곰](bear1.png)` → `data/images/bear1.png`)
- Links (unified rule using Markdown syntax): `[label](target)`
  - `https?://…` → external link
  - `/src`, `/data`, `./…`, `../…` → path link as-is
  - otherwise → internal page link (`wiki.php?a=view&title=target`)
- Wiki-style links `[[Page]]`, `[[Page|Label]]` also work for convenience.

Why
- Markdown-first wiki that works even on very old/shared PHP hosting without DB.

Supported Syntax (Markdown)
- Headings `#..######`, emphasis `*italic*` `**bold**`, inline code `` `code` ``
- Lists `-`/`+`/`*` and `1.`; blockquotes `>`; horizontal rules
- Fenced code blocks ``` ```; tables with alignment (`:---`, `---:`, `:---:`)
- Images: `![alt](url)`
  - External `http/https` or relative to `data/` (e.g., `img/logo.png`)
 - Task list items: `- [ ] Todo`, `- [x] Done`
 - Footnotes: `[^id]` with definitions `[^id]: text` (indented continuation supported)
 - Link titles: `[text](url "title")` or `[text](url 'title')`
 - Image titles: `![alt](url "title")`
 - Setext headings: `Heading` + underline `===` (H1) or `---` (H2)
 - Language-tagged fences: ```` ```js ```` renders `<pre class="lang-js">…</pre>`
- Line breaks: a single newline inside a paragraph becomes `<br />` (configurable)

Tables (example)
| Col A | Col B |
| :--- | ---: |
| left | right |

Security Notes
- All non-markup text is HTML-escaped.
- External links are restricted to `http/https`.
- Links include `rel="nofollow"` by default (configurable).
- Complete login protection: all access requires authentication.
- Session-based security with 3-hour timeout.

### IP Access Restriction (Recommended)
When running in loginless mode (default), restrict access to specific IP addresses using `.htaccess`:

1. Create `.htaccess` file in your `public/` directory
2. Add the following configuration:

```apache
# Allow access only from specific IPs
Order Deny,Allow
Deny from all
Allow from 123.456.789.0    # Your home/office IP
Allow from 111.222.333.444  # Additional trusted IP

# For Cafe24 hosting, you might need this format instead:
<RequireAll>
    Require ip 123.456.789.0
    Require ip 111.222.333.444
</RequireAll>
```

3. (Optional) If you enable login and instead want IP restriction for only edit actions:

```apache
# Restrict only edit actions
<FilesMatch "wiki\.php">
    <If "%{QUERY_STRING} =~ /a=(edit|save|delete|rename)/">
        Order Deny,Allow
        Deny from all
        Allow from 123.456.789.0
    </If>
</FilesMatch>
```

4. To find your current IP address:
   - Visit: https://whatismyipaddress.com/
   - Or run: `curl ifconfig.me` in terminal

**Note**: Replace example IPs with your actual IP addresses. Contact your hosting provider if `.htaccess` rules don't work as expected.

Project Layout
- `src/wiki_parser.php`: Markdown renderer + link rules.
- `src/wiki_engine.php`: Engine (storage, history, rename, helpers).
- `public/wiki.php`: Router (front/view/edit/save/search/history/rename/delete/new/login/logout/account/users).
- `data/pages/`: Pages `<Title>.md` including `FrontPage.md`, `SyntaxGuide.md`.

Theming
- Light/Dark theme toggle is available in FrontPage, Syntax guide, and Wiki UI.
- Preference is saved to `localStorage` and applied site-wide.

Requirements
- PHP 4.3+ (tested for compatibility in syntax; uses `preg_*` and `htmlspecialchars`).
- No database, no extensions required beyond PCRE (standard on PHP 4.x).

Quick Start
1. Upload the repository (e.g., `public/` under `public_html/`).
2. Ensure `data/` is writable (`chmod -R 775 data/`).
3. Add an `.htaccess` IP allowlist under `public/` (see above) to protect the site.
4. Visit `public/` — no login required by default.
5. (Optional) To enable login, set `WIKI_REQUIRE_LOGIN` to `true` in `public/wiki.php`, then log in as `admin / passw0rd` and change the password.

Admin Notes
- Rename updates wiki-style links (`[[Old]]`, `[[Old|Label]]`) best-effort.

## User Management
- Users are stored in `data/users.json`; passwords are MD5 hashed.
- Preferred: use the built-in UI — `Account` for changing your own password, `Users` (admin) to add/delete/reset.
- Manual (optional):
  1. Generate MD5 hash: `echo -n "newpassword" | md5sum`
  2. Edit `data/users.json` and replace the `password_hash` value

Limitations
- Minimal Markdown subset with selected extensions (tables, task lists, footnotes, link titles). Other extended syntaxes like strikethrough, reference-style links, Setext headings, and language-tagged code fences are not supported.
- Rename does not yet rewrite Markdown `[label](Page)` targets — only `[[Page]]` forms.
- For very large sites, run link updates in batches (rename UI has a per-request limit).


## Login Toggle Code Map
Use this map to locate the login toggle and related conditionals. Line numbers may shift slightly with edits; they are indicative.

- `public/wiki.php:7` — Define toggle: `define('WIKI_REQUIRE_LOGIN', false)`
- `public/index.php:6` — Same toggle for entrypoint
- `public/wiki.php:21` — Login enforcement wrapper; redirects to login only when `WIKI_REQUIRE_LOGIN` is true
- `public/wiki.php:195` — Top nav: account/users/logout shown only when `WIKI_REQUIRE_LOGIN` is true
- `public/wiki.php:407` — Save (`a=save`): login required check guarded by `WIKI_REQUIRE_LOGIN`
- `public/wiki.php:443` — Delete (`a=delete` POST): login required check guarded by `WIKI_REQUIRE_LOGIN`
- `public/wiki.php:545` — Rename (`a=rename` POST): login required check guarded by `WIKI_REQUIRE_LOGIN`
- `public/wiki.php:588` — Account route hidden when login disabled (404)
- `public/wiki.php:626` — Users route hidden when login disabled (404)
- `public/wiki.php:711` — Login route redirects to FrontPage when login disabled
- `public/wiki.php:773` — Logout route: behavior differs by toggle
- `public/index.php:11` — If login disabled, redirect straight to FrontPage; otherwise go to login if not signed in

Notes
- Users file is still initialized on boot: `public/wiki.php:14`, `public/index.php:9`.
- CSRF token checks remain active for write actions regardless of the toggle.
- Image assets (`a=asset`) are unaffected by the toggle; they follow overall access control (IP allowlist) and file-type checks.

License
- Provided as-is for legacy hosting compatibility. Use at your own discretion.
