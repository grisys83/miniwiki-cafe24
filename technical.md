# MiniWiki Technical Notes

## Architecture
- PHP 4–compatible, no framework, no DB.
- Router: `public/wiki.php` (actions: front, view, edit, save, all, search, history, rename, delete, new)
- Engine: `src/wiki_engine.php`
  - Storage
    - Pages: `data/pages/<Title>.md` (Title is `rawurlencode`d)
    - History: `data/history/<Title>/<STAMP>.md`
    - Canonical: `data/frontpage.md`, `data/syntaxguide.md`
  - Helpers: safe title, read/write (atomic temp file), history save, rename+link update, CSRF token
  - magic_quotes handling + Markdown quote cleanup (outside code)
- Renderer: `src/wiki_parser.php` → Markdown-only parser with unified links

## Rendering Pipeline
- Normalize newlines → split into lines
- Fenced code blocks (```)
- Markdown tables (header + delimiter + data; aligns: `:---`, `---:`, `:---:`)
- Headings, blockquotes, lists, HR
- Paragraph + hard-wrap (single newline → `<br />` when enabled)
- Inline: backticks → `<code>`, images `![alt](url)`, links
  - Unified `[label](target)` → external/path/internal
  - Wiki `[[Page]]` → internal
- Escape the rest (split on tags; HTML-escape only text segments)

## Links: Unified Rule
- `[label](target)`
  - `https?://…` → external
  - `/src`, `/data`, `./`, `../` → path
  - otherwise → internal page `wiki.php?a=view&title=target`
- Wiki style: `[[Page]]`, `[[Page|Label]]` remain supported

## Canonical Pages
- `FrontPage` → `/data/frontpage.md`
- `SyntaxGuide` → `/data/syntaxguide.md`
- Router reads/writes these files directly; history still tracked per title.

## Rename + Link Update
- `wiki_engine_rename_page(old,new,update_links,...)`
  - File move + history dir move/merge
  - Stub optional: `#REDIRECT [[New]]`
  - Link update (best-effort): rewrites `[[Old]]` / `[[Old|Label]]` outside code/inline code
  - Markdown `[label](Page)` rewriting is not implemented yet

## Security
- Escaping: All text segments are HTML-escaped.
- External links restricted to `http/https` + `rel="nofollow"` by default.
- CSRF token for save/delete/rename.
- Password: single backtick (`) for edit/delete/rename (hardcoded; changeable in code).
- magic_quotes: input normalized; extra backslashes before quotes removed in Markdown mode (outside code)

## File Layout
- `public/wiki.php` router; `public/` contains the entry points
- `src/wiki_engine.php` engine; `src/wiki_parser.php` renderer
- `data/` contains canonical pages, page storage, history
- `scripts/convert_txt_to_md.php` legacy conversion

## Compatibility
- Designed to run on PHP 4.x with PCRE; no composer.
- Avoids closures, namespaces, short array syntax.

## Extensibility
- Add Markdown extensions (tables are already supported; footnotes/task lists can be added similarly).
- Implement Markdown link rewriting in `wiki_engine_update_links` if needed.
- Add simple auth or cookie-based “remember me” for editor.

