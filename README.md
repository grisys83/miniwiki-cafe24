MiniWiki (Markdown Wiki for PHP4/Cafe24)

Overview
- Lightweight Markdown-only wiki engine for legacy PHP 4 hosting (e.g., Cafe24).
- No DB, no external libraries; pure PHP 4–compatible code.
- Canonical pages (FrontPage, SyntaxGuide) are managed as flat files under `/data/`.

Engine
- File-based wiki engine (PHP4 compatible).
- Routes: view, edit, save, all pages, search, history, rename, delete.
- Storage:
  - Pages: `data/pages/<Title>.md` (Title is `rawurlencode`d)
  - Canonical: `data/frontpage.md`, `data/syntaxguide.md`
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
- Edits require the fixed password: backtick character (`). Change `WIKI_EDIT_PASSWORD` in `src/wiki_engine.php`.

 Project Layout
- `src/wiki_parser.php`: Markdown renderer + link rules.
- `src/wiki_engine.php`: Engine (storage, history, rename, helpers).
- `public/wiki.php`: Router (view/edit/save/search/history/rename/delete/front).
- `data/frontpage.md`, `data/syntaxguide.md`: Canonical landing pages.
- `data/pages/`: User pages `<Title>.md`.
- `scripts/convert_txt_to_md.php`: Legacy `.txt` → `.md` converter.

Theming
- Light/Dark theme toggle is available in FrontPage, Syntax guide, and Wiki UI.
- Preference is saved to `localStorage` and applied site-wide.

Requirements
- PHP 4.3+ (tested for compatibility in syntax; uses `preg_*` and `htmlspecialchars`).
- No database, no extensions required beyond PCRE (standard on PHP 4.x).

 Quick Start
1. Upload the repository (e.g., `public/` under `public_html/`).
2. Ensure `data/` is writable (`chmod -R 775 data/`).
3. Visit `public/` → FrontPage.
4. Edit pages via the top action links (password: `).
5. (Optional) Convert legacy `.txt` pages: `php scripts/convert_txt_to_md.php`.

Admin Notes
- Rename updates wiki-style links (`[[Old]]`, `[[Old|Label]]`) best-effort.
- FrontPage/SyntaxGuide are canonical under `/data/`; editing those pages writes to those files.

Limitations
- Minimal Markdown subset (no footnotes or extended syntax).
- Rename does not yet rewrite Markdown `[label](Page)` targets — only `[[Page]]` forms.
- For very large sites, run link updates in batches (rename UI has a per-request limit).

Migration (from `.txt`)
- Run: `php scripts/convert_txt_to_md.php` (add `--dry-run` to simulate).

License
- Provided as-is for legacy hosting compatibility. Use at your own discretion.
