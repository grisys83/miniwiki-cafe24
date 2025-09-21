MiniWiki (Markdown Wiki for PHP4/Cafe24)

Overview
- Lightweight Markdown-only wiki engine for legacy PHP 4 hosting (e.g., Cafe24).
- No DB, no external libraries; pure PHP 4–compatible code.
- FrontPage and SyntaxGuide are regular pages under `data/pages/` (no special handling).

🔐 **LOGIN SYSTEM** 🔐
This wiki uses a session-based login system:
- Default admin account: username `admin`, password `passw0rd`
- All pages require login (including reading)
- Session timeout: 3 hours
- Login attempts: unlimited (no lockout)

Account & Users (UI)
- Account: logged-in users can change their own password at `wiki.php?a=account`.
- Users (admin only): list/add/delete users and reset passwords at `wiki.php?a=users`.
- JSON is still supported directly under `data/users.json` if needed.

Engine
- File-based wiki engine (PHP4 compatible).
- Routes: front, view, edit, save, all pages, search, history, rename, delete, new, login, logout, account, users.
- Storage:
  - Pages: `data/pages/<Title>.md` (Title is `rawurlencode`d)
  - History: `data/history/<Title>/<STAMP>.md`
- Edit/Delete protection: hardcoded password is a single backtick character: `

Rendering
- Markdown-only renderer with tables, fenced code, lists, headings, images, emphasis.
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
For additional security, restrict access to specific IP addresses using `.htaccess`:

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

3. For edit-only restriction (allow public viewing but restrict editing):

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
3. Visit `public/` → Login page will appear automatically.
4. Login with: username `admin`, password `passw0rd`
5. **IMPORTANT**: Change the default password by editing `data/users.json`
6. All wiki features are now available after login.

Admin Notes
- Rename updates wiki-style links (`[[Old]]`, `[[Old|Label]]`) best-effort.

## User Management
- Users are stored in `data/users.json`; passwords are MD5 hashed.
- Preferred: use the built-in UI — `Account` for changing your own password, `Users` (admin) to add/delete/reset.
- Manual (optional):
  1. Generate MD5 hash: `echo -n "newpassword" | md5sum`
  2. Edit `data/users.json` and replace the `password_hash` value

Limitations
- Minimal Markdown subset (no footnotes or extended syntax).
- Rename does not yet rewrite Markdown `[label](Page)` targets — only `[[Page]]` forms.
- For very large sites, run link updates in batches (rename UI has a per-request limit).


License
- Provided as-is for legacy hosting compatibility. Use at your own discretion.
