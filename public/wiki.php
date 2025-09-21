<?php
header('Content-Type: text/html; charset=utf-8');
require_once(dirname(__FILE__) . '/../src/wiki_parser.php');
require_once(dirname(__FILE__) . '/../src/wiki_engine.php');

// Ensure data directories exist
wiki_engine_mkdir_p(WIKI_DATA_DIR);
wiki_engine_mkdir_p(WIKI_HISTORY_DIR);

// Routing params
$a = isset($_GET['a']) ? $_GET['a'] : 'view';
$title = isset($_GET['title']) ? (string)$_GET['title'] : 'Home';
$title = wiki_engine_safe_title($title);

// Helper to render standard header/footer
function wiki_header($title_page, $subtitle, $current_title) {
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($title_page, ENT_QUOTES); ?> - Wiki</title>
  <style>
    :root { 
      color-scheme: light; 
      --bg: #f6f6f6; --text: #222; --muted: #666;
      --header-bg: #e6ecf8; --header-text: #1d3f8b; --header-link: #1d3f8b; --header-sub: #4a4a4a;
      --nav-bg: #eef3ff; --nav-text: #1d3f8b; --nav-link: #1d3f8b; --nav-border: #d5def5;
      --content-bg: #ffffff; --link: #1d3f8b;
      --pre-bg: #111111; --pre-text: #eeeeee; --code-bg: #1f1f1f; --code-text: #f0f0f0;
      --bq-border: #cccccc; --bq-bg: #fbfbfb; --bq-text: #555555;
      --table-border: #dddddd; --th-bg: #f3f3f3; --th-text: #333333;
      --btn-bg: #e6ecf8; --btn-text: #1d3f8b; --btn-border: #c7d2ea;
      --input-bg: #ffffff; --input-text: #222; --input-border: #cbd3ea;
    }
    [data-theme="dark"] { 
      color-scheme: dark; 
      --bg: #0f1115; --text: #e6e6e6; --muted: #aaa;
      --header-bg: #111827; --header-text: #e6e6e6; --header-link: #9ecbff; --header-sub: #bbb;
      --nav-bg: #0f172a; --nav-text: #e6e6e6; --nav-link: #c9e0ff; --nav-border: #1f2937;
      --content-bg: #111111; --link: #9ecbff;
      --pre-bg: #0c0c0c; --pre-text: #eeeeee; --code-bg: #1f1f1f; --code-text: #f0f0f0;
      --bq-border: #333333; --bq-bg: #1a1a1a; --bq-text: #cfcfcf;
      --table-border: #333333; --th-bg: #1d1d1d; --th-text: #ececec;
      --btn-bg: #1f2937; --btn-text: #e6e6e6; --btn-border: #2b3648;
      --input-bg: #0c0c0c; --input-text: #f2f2f2; --input-border: #333333;
    }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; margin: 0; background: var(--bg); color: var(--text); }
    header { background: var(--header-bg); color: var(--header-text); padding: 0.6rem 1rem; border-bottom: 1px solid var(--nav-border);}    
    header a { color: var(--header-link); text-decoration: none; }
    header .title { font-weight: 600; color: var(--header-link); }
    header .sub { color: var(--header-sub); font-size: 0.9rem; }
    header .nav { margin-top: 0.3rem; display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
    header .nav a, header .nav button { color: var(--header-link); text-decoration: none; }
    header .nav button { background: var(--btn-bg); color: var(--btn-text); border: 1px solid var(--btn-border); padding: 0.2rem 0.5rem; border-radius: 6px; cursor: pointer; }
    main { min-height: calc(100vh - 60px); }
    .content { padding: 1rem 1.25rem; background: var(--content-bg); color: var(--text); }
    .content a { color: var(--link); }
    .content pre { background: var(--pre-bg); color: var(--pre-text); padding: 0.75rem; border-radius: 6px; overflow: auto; }
    .content code { background: var(--code-bg); color: var(--code-text); padding: 0 0.25rem; border-radius: 4px; }
    .content blockquote { border-left: 4px solid var(--bq-border); background: var(--bq-bg); color: var(--bq-text); margin: 0; padding: 0.5rem 1rem; }
    .content table { border-collapse: collapse; width: 100%; background: transparent; }
    .content th, .content td { border: 1px solid var(--table-border); padding: 0.5rem; text-align: left; }
    .content th { background: var(--th-bg); color: var(--th-text); }
    .toolbar { margin: 0 0 1rem 0; display: flex; gap: 0.5rem; }
    .toolbar a, .toolbar button { background: var(--btn-bg); color: var(--btn-text); border: 1px solid var(--btn-border); padding: 0.4rem 0.6rem; border-radius: 6px; text-decoration: none; }
    textarea { width: 100%; height: 60vh; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; background: var(--input-bg); color: var(--input-text); border: 1px solid var(--input-border); border-radius: 8px; padding: 0.75rem; }
    input[type=text], input[type=password] { width: 100%; background: var(--input-bg); color: var(--input-text); border: 1px solid var(--input-border); border-radius: 6px; padding: 0.4rem 0.6rem; }
    header .nav form.search { margin-left: auto; display: flex; gap: 0.25rem; }
    header .nav form.search input[type=text] { width: 220px; }
    header .nav form.newpage { display: none; align-items: center; gap: 0.25rem; }
    header .nav form.newpage input[type=text] { width: 220px; }
    .muted { color: var(--muted); }
    .empty { color: var(--muted); font-style: italic; }
  </style>
  <script>
    (function(){
      var key='miniTheme';
      try {
        var saved = localStorage.getItem(key) || 'light';
        document.documentElement.setAttribute('data-theme', saved);
      } catch(e) {
        document.documentElement.setAttribute('data-theme', 'light');
      }
      window.toggleTheme = function(){
        var cur = document.documentElement.getAttribute('data-theme') || 'light';
        var next = (cur === 'light') ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem(key, next); } catch(e){}
        var btns = document.querySelectorAll('[data-theme-toggle]');
        for (var i=0;i<btns.length;i++) { btns[i].textContent = (next === 'light') ? 'Dark' : 'Light'; }
      };
      document.addEventListener('DOMContentLoaded', function(){
        var cur = document.documentElement.getAttribute('data-theme') || 'light';
        var btns = document.querySelectorAll('[data-theme-toggle]');
        for (var i=0;i<btns.length;i++) { btns[i].textContent = (cur === 'light') ? 'Dark' : 'Light'; }
        var nf = document.getElementById('newpage-form');
        var ni = document.getElementById('newpage-input');
        var nl = document.getElementById('newpage-link');
        if (nl && nf) {
          nl.addEventListener('click', function(ev){
            if (ev && ev.preventDefault) ev.preventDefault();
            if (nf.style.display === 'flex') { nf.style.display = 'none'; }
            else { nf.style.display = 'flex'; try { ni && ni.focus(); } catch(e){} }
            return false;
          });
        }
      });
    })();
  </script>
</head>
<body>
  <header>
    <div class="title">MiniWiki</div>
    <div class="nav">
      <a href="index.php">FrontPage</a>
      <a href="wiki.php?a=search">FindPage</a>
      <a href="wiki.php?a=all">TitleIndex</a>
      <a href="wiki.php?a=recent">RecentChanges</a>
      <a href="wiki.php?a=view&amp;title=SyntaxGuide">SyntaxGuide</a>
<?php
      $ct = trim((string)$current_title);
      if ($ct !== '') {
          $exists = wiki_engine_exists($ct);
          echo ' <a href="' . htmlspecialchars('wiki.php?a=view&title=' . rawurlencode($ct), ENT_QUOTES) . '">View</a>';
          echo ' <a href="' . htmlspecialchars('wiki.php?a=edit&title=' . rawurlencode($ct), ENT_QUOTES) . '">Edit</a>';
          echo ' <a href="' . htmlspecialchars('wiki.php?a=rename&title=' . rawurlencode($ct), ENT_QUOTES) . '">Rename</a>';
          echo ' <a href="' . htmlspecialchars('wiki.php?a=history&title=' . rawurlencode($ct), ENT_QUOTES) . '">History</a>';
          if ($exists) {
            echo ' <a href="' . htmlspecialchars('wiki.php?a=delete&title=' . rawurlencode($ct), ENT_QUOTES) . '" style="color:#ff9aa2">Delete</a>';
          }
      }
?>
      <a href="#" id="newpage-link">New</a>
      <button type="button" onclick="toggleTheme()" data-theme-toggle>Dark</button>
      <form class="newpage" id="newpage-form" method="get" action="wiki.php">
        <input type="hidden" name="a" value="new" />
        <input type="text" id="newpage-input" name="new_title" placeholder="New page title" />
        <button type="submit">Create</button>
      </form>
      <form class="search" method="get" action="wiki.php">
        <input type="hidden" name="a" value="search" />
        <input type="text" name="q" placeholder="Search..." />
        <button type="submit">Search</button>
      </form>
    </div>
    <div class="sub"><?php echo htmlspecialchars($subtitle, ENT_QUOTES); ?></div>
  </header>
  <main>
    <div class="content">
<?php
}

function wiki_footer() {
?>
    </div>
  </main>
</body>
</html>
<?php
}

function wiki_url($a, $title) {
    $t = rawurlencode($title);
    return 'wiki.php?a=' . urlencode($a) . '&title=' . $t;
}

// Actions
// Front page (landing)
function wiki_list_recent_changes_cmp($a, $b) { if ($a['mtime']==$b['mtime']) return 0; return ($a['mtime']>$b['mtime'])?-1:1; }
function wiki_list_recent_changes($limit) {
    $pages = wiki_engine_list_pages();
    $items = array();
    for ($i=0; $i<count($pages); $i++) {
        $t = $pages[$i];
        $file = wiki_engine_filename_for_title($t);
        $mtime = @filemtime($file); if ($mtime === false) $mtime = 0;
        $items[] = array('title'=>$t,'mtime'=>$mtime);
    }
    if (count($items)) { usort($items, 'wiki_list_recent_changes_cmp'); }
    if ($limit>0 && count($items)>$limit) { $items = array_slice($items,0,$limit); }
    return $items;
}
if ($a === 'front') {
    $frontTitle = 'FrontPage';
    $fp_src = wiki_engine_read_canonical_frontpage();
    if ($fp_src === '') {
        $default = "# FrontPage\n\nWelcome to MiniWiki.\n\n- Edit this FrontPage to customize your wiki.\n- See [[SyntaxGuide|Syntax Guide]] for markup. ([View raw](/data/syntaxguide.md))\n";
        wiki_engine_save_canonical_frontpage($default);
        $fp_src = $default;
    }
    wiki_header('FrontPage', 'Viewing: FrontPage', $frontTitle);
    echo wiki_render($fp_src, array('auto_link' => true, 'hard_wrap' => true, 'rel_nofollow' => false, 'internal_links' => true, 'internal_base' => 'wiki.php', 'internal_param' => 'title'));
    wiki_footer();
    exit;
}

if ($a === 'view') {
    if ($title === 'SyntaxGuide') {
        $canon = wiki_engine_read_canonical_syntaxguide();
        if ($canon === '') {
            // Seed default SyntaxGuide into data/syntaxguide.md if missing
            $default = "# Wiki Syntax Guide\n\n" .
                "## Headings\n" .
                "# H1\n## H2\n### H3\n\n" .
                "## Emphasis\n" .
                "This is *italic*, this is **bold**, and this is **_bold italic_**.\n\n" .
                "## Inline code\n" .
                "Use `code()` inline.\n\n" .
                "## Links\n" .
                "Internal links: [[Home]] or [[Home|Go Home]].\nExternal links: https://example.com (auto-link).\nUnified: [Label](Home) or [OpenAI](https://openai.com).\n\n" .
                "## Images\n" .
                "![Placeholder](https://via.placeholder.com/80)\n\n" .
                "## Lists\n" .
                "- First item\n- Second item\n  - Nested child\n1. Numbered one\n2. Numbered two\n\n" .
                "## Blockquote\n" .
                "> A quoted line\n> continues here\n\n" .
                "## Code block\n" .
                "```\nfunction hello() {\n  return 'world';\n}\n```\n\n" .
                "---\n\n" .
                "## Table\n" .
                "| A | B | C |\n| :--- | :---: | ---: |\n| left | center | right |\n";
            wiki_engine_save_canonical_syntaxguide($default);
            $canon = $default;
        }
    }
    if ($title === 'SyntaxGuide' && !wiki_engine_exists($title)) {
        if (defined('WIKI_RENDERER') && constant('WIKI_RENDERER') === 'markdown_wiki') {
            $default = "# Wiki Syntax Guide\n\n" .
                "## Headings\n" .
                "# H1\n## H2\n### H3\n\n" .
                "## Emphasis\n" .
                "This is *italic*, this is **bold**, and this is **_bold italic_**.\n\n" .
                "## Inline code\n" .
                "Use `code()` inline.\n\n" .
                "## Links\n" .
                "Internal links use wiki syntax: [[Home]] or [[Home|Go Home]].\nExternal links: https://example.com (auto-link).\n\n" .
                "## Images\n" .
                "![Placeholder](https://via.placeholder.com/80)\n\n" .
                "## Lists\n" .
                "- First item\n" .
                "- Second item\n" .
                "  - Nested child\n" .
                "1. Numbered one\n" .
                "2. Numbered two\n\n" .
                "## Blockquote\n" .
                "> A quoted line\n> continues here\n\n" .
                "## Code block\n" .
                "```\nfunction hello() {\n  return 'world';\n}\n```\n\n" .
                "---\n\n";
        } else {
            $default = "= Wiki Syntax Guide =\n\n" .
                "== Headings ==\n" .
                "= H1 =\n== H2 ==\n=== H3 ===\n\n" .
                "== Emphasis ==\n" .
                "This is ''italic'', this is '''bold''', and this is '''''bold italic'''''.\n\n" .
                "== Inline code ==\n" .
                "Use {{{code()}}} or `inline()`.\n\n" .
                "== Links ==\n" .
                "[[https://example.com|Example Link]] and [[Label|https://example.org]].\nAlso: [https://example.com Inline label].\nInternal: [[Home]] or [[Home|Go Home]].\n\n" .
                "== Images ==\n" .
                "[[Image:https://via.placeholder.com/80|Placeholder]]\n\n" .
                "== Lists ==\n" .
                "* First item\n" .
                "* Second item\n" .
                "** Nested child\n" .
                "# Numbered one\n" .
                "# Numbered two\n\n" .
                "== Blockquote ==\n" .
                "> A quoted line\n> continues here\n\n" .
                "== Code block ==\n" .
                "{{{\nfunction hello() {\n  return 'world';\n}\n}}}\n\n" .
                "----\n\n" .
                "== Table ==\n" .
                "{|\n" .
                "! Header A !! Header B !! Header C\n" .
                "|-\n" .
                "| Cell A1 || Cell B1 || Cell C1\n" .
                "|-\n" .
                "| Cell A2 || Cell B2 || Cell C2\n" .
                "|}\n";
        }
        wiki_engine_save_page($title, $default);
    }
    if ($title === 'SyntaxGuide') {
        $src = wiki_engine_read_canonical_syntaxguide();
    } else {
        if ($title === 'FrontPage') { $src = wiki_engine_read_canonical_frontpage(); }
        else { $src = wiki_engine_read_page($title); }
    }
    $subtitle = 'Viewing: ' . $title;
    wiki_header($title, $subtitle, $title);
    if ($src === '') {
        echo '<p class="empty">This page does not exist. <a href="' . htmlspecialchars(wiki_url('edit', $title), ENT_QUOTES) . '">Create it?</a></p>';
    } else {
        // Handle redirect stub: #REDIRECT [[Target]]
        $src_trim = ltrim($src);
        if (preg_match('/^#REDIRECT\s*\[\[([^\]]+)\]\]/i', $src_trim, $rm)) {
            $target = trim($rm[1]);
            if ($target !== '') {
                header('Location: ' . wiki_url('view', $target));
                echo '<p class="muted">Redirecting to ' . htmlspecialchars($target, ENT_QUOTES) . '...</p>';
                wiki_footer();
                exit;
            }
        }
        $html = wiki_render($src, array('auto_link' => true, 'hard_wrap' => true, 'rel_nofollow' => false, 'internal_links' => true, 'internal_base' => 'wiki.php', 'internal_param' => 'title'));
        echo $html;
    }
    wiki_footer();
    exit;
}

if ($a === 'new') {
    $new_title = '';
    if (isset($_GET['new_title'])) { $new_title = (string)$_GET['new_title']; }
    if (isset($_POST['new_title'])) { $new_title = (string)$_POST['new_title']; }
    $new_title = wiki_engine_unmagic($new_title);
    $new_title = wiki_engine_safe_title($new_title);
    if ($new_title === '') {
        wiki_header('New Page', 'Please enter a page title.', '');
        echo '<p class="empty">Please enter a page title.</p>';
        wiki_footer();
        exit;
    }
    if (wiki_engine_exists($new_title)) {
        wiki_header('New Page', 'Title already exists', $new_title);
        echo '<p class="empty">A page named “' . htmlspecialchars($new_title, ENT_QUOTES) . '” already exists. <a href="' . htmlspecialchars(wiki_url('view', $new_title), ENT_QUOTES) . '">Open it</a> or choose a different title.</p>';
        wiki_footer();
        exit;
    }
    header('Location: ' . wiki_url('edit', $new_title));
    exit;
}

if ($a === 'edit') {
    if ($title === 'SyntaxGuide') { $src = wiki_engine_read_canonical_syntaxguide(); }
    else if ($title === 'FrontPage') { $src = wiki_engine_read_canonical_frontpage(); }
    else { $src = wiki_engine_read_page($title); }
    $subtitle = 'Editing: ' . $title;
    wiki_header($title, $subtitle, $title);
    $token = wiki_engine_csrf_token();
?>
    <form method="post" action="<?php echo htmlspecialchars(wiki_url('save', $title), ENT_QUOTES); ?>">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>" />
      <p class="muted">Page: <strong><?php echo htmlspecialchars($title, ENT_QUOTES); ?></strong></p>
      <p class="muted">Password required to save:</p>
      <p><input type="password" name="pw" placeholder="Password" /></p>
      <textarea name="src" spellcheck="false"><?php echo htmlspecialchars($src, ENT_QUOTES); ?></textarea>
      <p>
        <button type="submit">Save</button>
        <a class="muted" href="<?php echo htmlspecialchars(wiki_url('view', $title), ENT_QUOTES); ?>">Cancel</a>
      </p>
    </form>
<?php
    wiki_footer();
    exit;
}

if ($a === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . wiki_url('view', $title)); exit; }
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    if (!wiki_engine_verify_token($token)) { header('HTTP/1.1 403 Forbidden'); echo 'Invalid token'; exit; }
    $pw = isset($_POST['pw']) ? (string)$_POST['pw'] : '';
    if (!wiki_engine_check_password($pw)) { header('HTTP/1.1 403 Forbidden'); echo 'Invalid password'; exit; }
    $src = isset($_POST['src']) ? (string)$_POST['src'] : '';
    // Undo magic_quotes_gpc so backslashes don't accumulate
    $src = wiki_engine_unmagic($src);
    // In Markdown mode, remove unnecessary backslashes before quotes outside code
    if (wiki_engine_is_markdown_mode()) {
        $src = wiki_engine_cleanup_quotes_for_markdown($src);
    }
    if ($title === 'SyntaxGuide') { wiki_engine_save_canonical_syntaxguide($src); }
    else if ($title === 'FrontPage') { wiki_engine_save_canonical_frontpage($src); }
    else { wiki_engine_save_page($title, $src); }
    header('Location: ' . wiki_url('view', $title));
    exit;
}

if ($a === 'all') {
    $pages = wiki_engine_list_pages();
    $subtitle = 'All Pages (' . count($pages) . ')';
    wiki_header('All Pages', $subtitle, '');
    if (!count($pages)) {
        echo '<p class="empty">No pages yet.</p>';
    } else {
        echo '<ul>';
        for ($i = 0; $i < count($pages); $i++) {
            $p = $pages[$i];
            echo '<li><a href="' . htmlspecialchars(wiki_url('view', $p), ENT_QUOTES) . '">' . htmlspecialchars($p, ENT_QUOTES) . '</a></li>';
        }
        echo '</ul>';
    }
    wiki_footer();
    exit;
}

if ($a === 'delete') {
    $subtitle = 'Delete: ' . $title;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        $pw = isset($_POST['pw']) ? (string)$_POST['pw'] : '';
        $pw = wiki_engine_unmagic($pw);
        if (!wiki_engine_verify_token($token)) { header('HTTP/1.1 403 Forbidden'); echo 'Invalid token'; exit; }
        if (!wiki_engine_check_password($pw)) { header('HTTP/1.1 403 Forbidden'); echo 'Invalid password'; exit; }
        wiki_engine_delete_page($title);
        header('Location: ' . 'wiki.php?a=all');
        exit;
    }
    wiki_header('Delete', $subtitle, $title);
    $token = wiki_engine_csrf_token();
    if (!wiki_engine_exists($title)) {
        echo '<p class="empty">Page already deleted.</p>';
    } else {
?>
    <form method="post" action="<?php echo htmlspecialchars(wiki_url('delete', $title), ENT_QUOTES); ?>">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>" />
      <p>Delete page <strong><?php echo htmlspecialchars($title, ENT_QUOTES); ?></strong>?</p>
      <p class="muted">This removes the current page file but keeps a copy in history.</p>
      <p><input type="password" name="pw" placeholder="Password" /></p>
      <p>
        <button type="submit" style="background:#3a0000;border-color:#550000">Delete</button>
        <a class="muted" href="<?php echo htmlspecialchars(wiki_url('view', $title), ENT_QUOTES); ?>">Cancel</a>
      </p>
    </form>
<?php
    }
    wiki_footer();
    exit;
}

if ($a === 'search') {
    $q = isset($_GET['q']) ? (string)$_GET['q'] : '';
    $results = ($q !== '') ? wiki_engine_search($q, 200) : array();
    $subtitle = 'Search';
    wiki_header('Search', $subtitle, '');
?>
    <form method="get" action="wiki.php" style="margin-bottom: 1rem;">
      <input type="hidden" name="a" value="search" />
      <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>" placeholder="Search..." />
      <button type="submit">Search</button>
    </form>
<?php
    if ($q === '') {
        echo '<p class="muted">Enter a search term.</p>';
    } else if (!count($results)) {
        echo '<p class="empty">No matches.</p>';
    } else {
        echo '<p class="muted">Results: ' . count($results) . '</p>';
        echo '<ul>';
        for ($i = 0; $i < count($results); $i++) {
            $p = $results[$i];
            echo '<li><a href="' . htmlspecialchars(wiki_url('view', $p), ENT_QUOTES) . '">' . htmlspecialchars($p, ENT_QUOTES) . '</a></li>';
        }
        echo '</ul>';
    }
    wiki_footer();
    exit;
}

if ($a === 'recent') {
    $recent = wiki_list_recent_changes(50);
    $subtitle = 'Recent Changes';
    wiki_header('RecentChanges', $subtitle, '');
    if (!count($recent)) {
        echo '<p class="muted">No pages yet.</p>';
    } else {
        echo '<ul class="recent">';
        for ($i=0;$i<count($recent);$i++) { $t=$recent[$i]['title']; $mt=$recent[$i]['mtime'];
            echo '<li><a href="' . 'wiki.php?a=view&amp;title=' . rawurlencode($t) . '">' . htmlspecialchars($t, ENT_QUOTES) . '</a> <span class="muted">&middot; ' . date('Y-m-d H:i',$mt) . '</span></li>';
        }
        echo '</ul>';
    }
    wiki_footer();
    exit;
}

if ($a === 'history') {
    $subtitle = 'History: ' . $title;
    $hist = wiki_engine_list_history($title);
    wiki_header('History', $subtitle, $title);
    if (!count($hist)) {
        echo '<p class="empty">No history yet.</p>';
    } else {
        echo '<ul>';
        for ($i = 0; $i < count($hist); $i++) {
            $f = $hist[$i];
            $stamp = substr($f, 0, strlen($f) - 4);
            echo '<li>' . htmlspecialchars($stamp, ENT_QUOTES) . '</li>';
        }
        echo '</ul>';
    }
    wiki_footer();
    exit;
}

if ($a === 'rename') {
    $subtitle = 'Rename: ' . $title;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        $pw = isset($_POST['pw']) ? (string)$_POST['pw'] : '';
        $new_title = isset($_POST['new_title']) ? (string)$_POST['new_title'] : '';
        $update_links = isset($_POST['update_links']) && $_POST['update_links'] === '1';
        $leave_stub = isset($_POST['leave_stub']) ? ($_POST['leave_stub'] === '1') : true;
        $update_limit = isset($_POST['update_limit']) ? (int)$_POST['update_limit'] : 0;
        // Undo magic quotes on inputs
        $pw = wiki_engine_unmagic($pw);
        $new_title = wiki_engine_unmagic($new_title);
        if (!wiki_engine_verify_token($token)) { header('HTTP/1.1 403 Forbidden'); echo 'Invalid token'; exit; }
        if (!wiki_engine_check_password($pw)) { header('HTTP/1.1 403 Forbidden'); echo 'Invalid password'; exit; }
        $err = '';
        if (wiki_engine_rename_page($title, $new_title, $update_links, $err, $leave_stub, $update_limit)) {
            // Show summary page
            wiki_header('Rename', 'Renamed: ' . htmlspecialchars($title, ENT_QUOTES) . ' → ' . htmlspecialchars($new_title, ENT_QUOTES), $new_title);
            if ($update_links) {
                if ($update_limit > 0) {
                    echo '<p class="muted">Link update processed up to ' . (int)$update_limit . ' page(s) this request. Repeat rename with the same titles to process more, or set a higher limit.</p>';
                } else {
                    echo '<p class="muted">Link update processed for all pages (best-effort, code/pre excluded).</p>';
                }
            } else {
                echo '<p class="muted">Links in other pages were not updated.</p>';
            }
            wiki_footer();
            exit;
        } else {
            wiki_header('Rename', $subtitle, $title);
            echo '<p class="empty">Rename failed: ' . htmlspecialchars($err, ENT_QUOTES) . '</p>';
            wiki_footer();
            exit;
        }
    }
    wiki_header('Rename', $subtitle, $title);
    $token = wiki_engine_csrf_token();
?>
    <form method="post" action="<?php echo htmlspecialchars(wiki_url('rename', $title), ENT_QUOTES); ?>">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>" />
      <p>Rename <strong><?php echo htmlspecialchars($title, ENT_QUOTES); ?></strong> to:</p>
      <p><input type="text" name="new_title" placeholder="New title" /></p>
      <p><label><input type="checkbox" name="update_links" value="1" checked /> Update links in other pages</label></p>
      <p><label><input type="checkbox" name="leave_stub" value="1" checked /> Leave redirect stub at old title</label></p>
      <p><label>Update limit per request (optional): <input type="text" name="update_limit" size="6" placeholder="e.g. 50" /></label></p>
      <p><input type="password" name="pw" placeholder="Password" /></p>
      <p>
        <button type="submit">Rename</button>
        <a class="muted" href="<?php echo htmlspecialchars(wiki_url('view', $title), ENT_QUOTES); ?>">Cancel</a>
      </p>
    </form>
<?php
    wiki_footer();
    exit;
}

// Fallback: default to view
header('Location: ' . wiki_url('view', $title));
exit;

?>
