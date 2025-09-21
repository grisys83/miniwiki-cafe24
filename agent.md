# MiniWiki Agent Guide

This guide describes how to operate and maintain MiniWiki in a legacy PHP 4 hosting environment (e.g., Cafe24).

## Daily Ops
- Login: All access requires login (session-based, 3-hour timeout).
- Edit Pages: Use the top action links (Edit/Rename/History/Delete).
- Create Pages: Click New in the header, enter a unique title; you will be taken to Edit.
- FrontPage/SyntaxGuide: Regular wiki pages stored under `data/pages/FrontPage.md` and `data/pages/SyntaxGuide.md`.
- Search/All Pages: Use the header search or the All Pages route for overview tasks.

## Content Rules
- Markdown-first: headings, lists, blockquotes, fenced code, tables, images.
- Links (unified): `[label](target)`
  - `https?://…` → external
  - `/src`, `/data`, `./`, `../` → path link as-is
  - otherwise → internal page (`wiki.php?a=view&title=target`)
- Wiki links: `[[Page]]`, `[[Page|Label]]` still work.

## Admin Tasks
- Convert legacy `.txt` pages to `.md`:
  - `php scripts/convert_txt_to_md.php` (use `--dry-run` to preview)
- Rename Pages:
  - Use Rename; optionally update links (best-effort for `[[Old]]` → `[[New]]`).
  - For large sites, set a per-request update limit and repeat.
- Users/Admin UI:
  - Account: `wiki.php?a=account` (change own password)
  - Users (admin): `wiki.php?a=users` (list/add/delete/reset)
- Backups:
  - Pages: `data/pages/*.md`
  - History: `data/history/**.md`
  - Users: `data/users.json` (contains MD5 password hashes; protect appropriately)

## Security Notes
- Login required for all actions (session-based, 3-hour timeout).
- All non-markup text is HTML-escaped; external links are limited to http/https and add `rel="nofollow"` by default.
- magic_quotes: The engine strips magic quotes and cleans stray backslashes before quotes in Markdown mode.
- Do not deploy diagnostic files (e.g., `public/md5test.php`, `public/userstest.php`).

## Theming
- Light/Dark toggle is built into the header; preference is stored in localStorage.

## Troubleshooting
- “Cannot modify header information”: Ensure no stray output in included PHP files; end files without `?>`.
- Changes not persisted: Check `data/` permissions (`chmod -R 775 data/`).
- Link didn’t update on rename: Only `[[Old]]` forms are rewritten currently; rewrite Markdown links manually for now.
