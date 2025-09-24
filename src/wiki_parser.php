<?php
// PHP4-compatible Markdown renderer with wiki-style links

if (!function_exists('wiki_escape_html')) {
    function wiki_escape_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
}
if (!function_exists('wiki_sanitize_url')) {
    function wiki_sanitize_url($url) {
        $url = trim(html_entity_decode($url));
        if (preg_match('/^(https?:)\/\//i', $url)) return $url;
        return '';
    }
}
if (!function_exists('wiki_attr')) {
    function wiki_attr($s) {
        $s = str_replace("\r", '', $s);
        $s = str_replace("\n", ' ', $s);
        return htmlspecialchars($s, ENT_QUOTES);
    }
}

// Markdown blocks + unified links + wiki-style [[...]]
if (!function_exists('md_parse')) {
    function md_parse($text, $options = array()) {
        if (!is_array($options)) { $options = array(); }
        $auto_link     = isset($options['auto_link']) ? (bool)$options['auto_link'] : false;
        $rel_nofollow  = !isset($options['rel_nofollow']) || (bool)$options['rel_nofollow'];
        $hard_wrap     = !isset($options['hard_wrap']) || (bool)$options['hard_wrap'];
        $internal_base = isset($options['internal_base']) ? (string)$options['internal_base'] : 'wiki.php';
        $internal_param = isset($options['internal_param']) ? (string)$options['internal_param'] : 'title';

        $GLOBALS['__wiki_rel_nofollow'] = $rel_nofollow;
        $GLOBALS['__wiki_internal_base'] = $internal_base;
        $GLOBALS['__wiki_internal_param'] = $internal_param;

        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        $lines = explode("\n", $text);
        $out = '';
        $para_open = false;
        $in_pre = false;
        $pre_buffer = '';
        $pre_lang = '';
        $in_blockquote = false;
        $list_stack = array(); // 'ul' or 'ol'
        $li_open = array();

        // Initialize footnote state
        $GLOBALS['__md_footnote_defs'] = array();        // id => raw text
        $GLOBALS['__md_footnote_order'] = array();       // id => number (1-based)
        $GLOBALS['__md_footnote_seq'] = 0;               // counter

        $total = count($lines);
        for ($i = 0; $i < $total; $i++) {
            $line = $lines[$i];
            $trim = rtrim($line);

            if ($in_pre) {
                if (trim($line) === '```') {
                    $out .= '<pre' . ($pre_lang !== '' ? ' class="lang-' . wiki_attr($pre_lang) . '"' : '') . '>' . wiki_escape_html($pre_buffer) . "</pre>\n";
                    $in_pre = false; $pre_buffer = ''; $pre_lang = '';
                    continue;
                } else { $pre_buffer .= $line . "\n"; continue; }
            }

            // Fenced code (``` or ```lang)
            if (preg_match('/^\s*```([A-Za-z0-9_\-]+)?\s*$/', $line, $mf)) {
                md_close_paragraph($out, $para_open);
                md_close_lists_all($out, $list_stack, $li_open);
                if ($in_blockquote) { $out .= "</blockquote>\n"; $in_blockquote = false; }
                $in_pre = true; $pre_buffer = ''; $pre_lang = (isset($mf[1]) ? trim($mf[1]) : '');
                continue;
            }

            // Footnote definition: [^id]: text (with following indented lines)
            if (preg_match('/^\s*\[\^([^\]]+)\]:\s*(.*)$/', $line, $fd)) {
                $fid = trim($fd[1]);
                $ftext = isset($fd[2]) ? $fd[2] : '';
                // Collect subsequent indented lines (>=2 spaces or a tab)
                while ($i + 1 < $total) {
                    $peek = $lines[$i + 1];
                    if (preg_match('/^(\s{2,}|\t)(.*)$/', $peek, $fm)) {
                        $ftext .= "\n" . $fm[2];
                        $i++;
                    } else {
                        break;
                    }
                }
                if (!isset($GLOBALS['__md_footnote_defs'][$fid])) {
                    $GLOBALS['__md_footnote_defs'][$fid] = trim($ftext);
                } else {
                    // merge definitions if repeated
                    $prev = $GLOBALS['__md_footnote_defs'][$fid];
                    $join = $prev === '' ? trim($ftext) : ($prev . "\n" . trim($ftext));
                    $GLOBALS['__md_footnote_defs'][$fid] = $join;
                }
                // Do not emit output for footnote def lines
                continue;
            }

            // Markdown table block
            if (md_maybe_table_row($line)) {
                $next = ($i + 1 < $total) ? $lines[$i + 1] : '';
                $aligns = array();
                if (md_is_table_delim_line($next, $aligns)) {
                    md_close_paragraph($out, $para_open);
                    md_close_lists_all($out, $list_stack, $li_open);
                    if ($in_blockquote) { $out .= "</blockquote>\n"; $in_blockquote = false; }

                    $headers = md_table_cells($line);
                    while (count($aligns) < count($headers)) { $aligns[] = ''; }
                    $out .= "<table>\n<thead>\n<tr>";
                    for ($ci = 0; $ci < count($headers); $ci++) {
                        $cell = trim($headers[$ci]);
                        $style = md_align_style(isset($aligns[$ci]) ? $aligns[$ci] : '');
                        $out .= '<th' . ($style !== '' ? ' style="' . $style . '"' : '') . '>' . md_inline($cell, $options) . '</th>';
                    }
                    $out .= "</tr>\n</thead>\n<tbody>\n";

                    $i++;
                    while ($i + 1 < $total) {
                        $peek = $lines[$i + 1];
                        if (!md_maybe_table_row($peek)) { break; }
                        $i++;
                        $cells = md_table_cells($peek);
                        $out .= '<tr>';
                        for ($ci = 0; $ci < count($cells); $ci++) {
                            $cell = trim($cells[$ci]);
                            $style = md_align_style(isset($aligns[$ci]) ? $aligns[$ci] : '');
                            $out .= '<td' . ($style !== '' ? ' style="' . $style . '"' : '') . '>' . md_inline($cell, $options) . '</td>';
                        }
                        $out .= "</tr>\n";
                    }
                    $out .= "</tbody>\n</table>\n";
                    continue;
                }
            }

            // Horizontal rule
            if (preg_match('/^\s*([\-*\_])\1\1[\-\*\_]*\s*$/', $line)) {
                md_close_paragraph($out, $para_open);
                md_close_lists_all($out, $list_stack, $li_open);
                if ($in_blockquote) { $out .= "</blockquote>\n"; $in_blockquote = false; }
                $out .= "<hr />\n"; continue;
            }

            // Heading: # .. ######
            if (preg_match('/^\s{0,3}(#{1,6})\s*(.*?)\s*#*\s*$/', $line, $m)) {
                $level = strlen($m[1]); if ($level < 1) $level = 1; if ($level > 6) $level = 6;
                md_close_paragraph($out, $para_open);
                md_close_lists_all($out, $list_stack, $li_open);
                if ($in_blockquote) { $out .= "</blockquote>\n"; $in_blockquote = false; }
                $content = md_inline(trim($m[2]), $options);
                $out .= '<h' . $level . '>' . $content . '</h' . $level . ">\n";
                continue;
            }

            // Blockquote
            if (preg_match('/^\s*>\s?(.*)$/', $line, $mq)) {
                md_close_paragraph($out, $para_open);
                md_close_lists_all($out, $list_stack, $li_open);
                if (!$in_blockquote) { $out .= "<blockquote>\n"; $in_blockquote = true; }
                $out .= md_inline(trim($mq[1]), $options) . "<br />\n";
                continue;
            } else {
                if ($in_blockquote && trim($line) === '') { $out .= "</blockquote>\n"; $in_blockquote = false; }
                else if ($in_blockquote && !preg_match('/^\s*>/', $line)) { $out .= "</blockquote>\n"; $in_blockquote = false; }
            }

            // Lists (supports nested UL/OL and type switching)
            // Ordered markers: "1." or "1)". Unordered: -, +, *
            if (preg_match('/^(\s*)([-+*]|\d+[\.)])\s+(.*)$/', $line, $ml)) {
                $indent = strlen($ml[1]);
                $marker = $ml[2];
                $content_raw = $ml[3];
                $last_ch = substr($marker, -1);
                $type = ($last_ch === '.' || $last_ch === ')') ? 'ol' : 'ul';
                $ord = 1; if ($type === 'ol' && preg_match('/^(\d+)/', $marker, $mn)) { $ord = (int)$mn[1]; if ($ord < 1) $ord = 1; }
                // 2 spaces per nesting level (common in Markdown)
                $level = (int) floor($indent / 2);

                // Desired stack depth equals nesting level + 1 (the list holding this item)
                $desired = $level + 1;
                $cur = count($list_stack);

                // If we're deeper than desired, close lists up to desired depth
                if ($cur > $desired) {
                    for ($lvl = $cur - 1; $lvl >= $desired; $lvl--) {
                        if ($li_open[$lvl]) { $out .= "</li>\n"; $li_open[$lvl] = false; }
                        $out .= '</' . $list_stack[$lvl] . ">\n";
                        array_pop($list_stack); array_pop($li_open);
                    }
                    $cur = count($list_stack);
                }

                // If we're shallower than desired, open lists until desired depth
                if ($cur < $desired) {
                    for ($lvl = $cur; $lvl < $desired; $lvl++) {
                        // Use the current line's marker type for the deepest level; for
                        // intermediate jumps, default to the same type for consistency.
                        $open_type = ($lvl + 1 === $desired) ? $type : $type;
                        if ($open_type === 'ol' && ($lvl + 1 === $desired)) {
                            $out .= '<ol' . ($ord > 1 ? ' start="' . $ord . '"' : '') . ">\n";
                        } else {
                            $out .= '<' . $open_type . ">\n";
                        }
                        $list_stack[] = $open_type; $li_open[] = false;
                    }
                }

                // Same depth but list type changed: switch lists (close + open)
                $deep = count($list_stack) - 1;
                if ($deep >= 0 && $list_stack[$deep] !== $type) {
                    if ($li_open[$deep]) { $out .= "</li>\n"; $li_open[$deep] = false; }
                    $out .= '</' . $list_stack[$deep] . ">\n";
                    array_pop($list_stack); array_pop($li_open);
                    if ($type === 'ol') { $out .= '<ol' . ($ord > 1 ? ' start="' . $ord . '"' : '') . ">\n"; }
                    else { $out .= '<' . $type . ">\n"; }
                    $list_stack[] = $type; $li_open[] = false;
                    $deep = count($list_stack) - 1;
                }

                // Open or continue list item at current depth
                if (!$li_open[$deep]) { $out .= '<li>'; $li_open[$deep] = true; }
                else { $out .= "</li>\n<li>"; }

                // Task list item: [ ] or [x] prefix inside list item
                $content_trim = ltrim($content_raw);
                if (preg_match('/^\[( |x|X)\]\s+(.*)$/', $content_trim, $mtask)) {
                    $checked = ($mtask[1] === 'x' || $mtask[1] === 'X');
                    $label = isset($mtask[2]) ? $mtask[2] : '';
                    $out .= '<input type="checkbox" disabled' . ($checked ? ' checked="checked"' : '') . ' /> ' . md_inline(trim($label), $options);
                } else {
                    $out .= md_inline(trim($content_raw), $options);
                }

                // Close item if next line is not a list entry
                $next = ($i+1<$total) ? $lines[$i+1] : '';
                if (!preg_match('/^(\s*)([-+*]|\d+[\.)])\s+/', $next)) { $out .= "</li>\n"; $li_open[$deep] = false; }
                continue;
            } else {
                if (count($list_stack)) {
                    // Allow blank lines between list items without breaking the list
                    if (trim($line) === '') {
                        $deep = count($list_stack) - 1;
                        if ($deep >= 0 && $li_open[$deep]) { $out .= "</li>\n"; $li_open[$deep] = false; }
                        continue;
                    }
                    md_close_lists_all($out, $list_stack, $li_open);
                }
            }

            // Setext headings: line followed by = or - underline
            if (trim($line) !== '') {
                $next = ($i + 1 < $total) ? $lines[$i + 1] : '';
                if ($next !== '') {
                    if (preg_match('/^\s*=+\s*$/', $next)) {
                        md_close_paragraph($out, $para_open);
                        md_close_lists_all($out, $list_stack, $li_open);
                        if ($in_blockquote) { $out .= "</blockquote>\n"; $in_blockquote = false; }
                        $out .= '<h1>' . md_inline(trim($line), $options) . "</h1>\n";
                        $i++; // consume underline line
                        continue;
                    }
                    if (preg_match('/^\s*-{3,}\s*$/', $next)) {
                        md_close_paragraph($out, $para_open);
                        md_close_lists_all($out, $list_stack, $li_open);
                        if ($in_blockquote) { $out .= "</blockquote>\n"; $in_blockquote = false; }
                        $out .= '<h2>' . md_inline(trim($line), $options) . "</h2>\n";
                        $i++; // consume underline line
                        continue;
                    }
                }
            }

            // Blank line ends paragraph
            if (trim($line) === '') { md_close_paragraph($out, $para_open); continue; }

            // Paragraph
            if (!$para_open) { $out .= '<p>'; $para_open = true; }
            else { $out .= $hard_wrap ? "<br />\n" : "\n"; }
            $out .= md_inline(trim($line), $options);
        }

        if ($in_pre) { $out .= '<pre' . ($pre_lang !== '' ? ' class="lang-' . wiki_attr($pre_lang) . '"' : '') . '>' . wiki_escape_html($pre_buffer) . "</pre>\n"; }
        if ($in_blockquote) { $out .= "</blockquote>\n"; }
        if (count($list_stack)) { md_close_lists_all($out, $list_stack, $li_open); }
        md_close_paragraph($out, $para_open);

        // Append footnotes section if any references were used
        if (isset($GLOBALS['__md_footnote_order']) && is_array($GLOBALS['__md_footnote_order']) && count($GLOBALS['__md_footnote_order'])) {
            // Sort by assigned order
            $order = $GLOBALS['__md_footnote_order'];
            asort($order);
            $out .= "<div class=\"footnotes\">\n<hr />\n<ol>\n";
            foreach ($order as $fid => $num) {
                $def = isset($GLOBALS['__md_footnote_defs'][$fid]) ? $GLOBALS['__md_footnote_defs'][$fid] : '';
                $html = md_inline($def, $options);
                $out .= '<li id="fn-' . wiki_attr($fid) . '">' . $html . ' <a href="#fnref-' . wiki_attr($fid) . '" class="footnote-backref">&#8617;</a></li>' . "\n";
            }
            $out .= "</ol>\n</div>\n";
        }
        return $out;
    }

    function md_close_paragraph(&$out, &$para_open) { if ($para_open) { $out .= "</p>\n"; $para_open = false; } }
    function md_close_lists_all(&$out, &$list_stack, &$li_open) {
        for ($lvl = count($list_stack) - 1; $lvl >= 0; $lvl--) {
            if ($li_open[$lvl]) { $out .= "</li>\n"; $li_open[$lvl] = false; }
            $out .= '</' . $list_stack[$lvl] . ">\n";
        }
        $list_stack = array(); $li_open = array();
    }

    function md_inline($text, $options) {
        // Inline code
        if (!function_exists('md_cb_inline_code_backticks')) { function md_cb_inline_code_backticks($m) { return '<code>' . wiki_escape_html($m[1]) . '</code>'; } }
        $text = preg_replace_callback('/`([^`\n]+)`/', 'md_cb_inline_code_backticks', $text);

        // Images: ![alt](url) with optional title: ![alt](url "title") or ![alt](url 'title')
        if (!function_exists('md_cb_image')) {
            function md_cb_image($m) {
                $alt = trim($m[1]);
                $raw = isset($m[2]) ? trim($m[2]) : '';
                $title = '';
                if (isset($m[3]) && $m[3] !== '') { $title = $m[3]; }
                else if (isset($m[4]) && $m[4] !== '') { $title = $m[4]; }
                $src = '';
                if (preg_match('/^https?:\/\//i', $raw)) {
                    $url = wiki_sanitize_url($raw);
                    if ($url === '') return wiki_escape_html($m[0]);
                    $src = $url;
                } else {
                    // Treat non-http(s) as path under data/ (served via wiki.php?a=asset)
                    $path = $raw;
                    if ((strlen($path) >= 2) && (($path{0} === '"' && substr($path,-1) === '"') || ($path{0} === "'" && substr($path,-1) === "'"))) {
                        $path = substr($path, 1, strlen($path)-2);
                    }
                    $src = wiki_build_asset_href($path);
                }
                $attrs = ' src="' . wiki_attr($src) . '" alt="' . wiki_attr($alt) . '"';
                if ($title !== '') { $attrs .= ' title="' . wiki_attr($title) . '"'; }
                return '<img' . $attrs . ' />';
            }
        }
        $text = preg_replace_callback("/!\[([^\]]*)\]\(([^\s\)]+)(?:\s+\"([^\"]*)\"|\s+'([^']*)')?\)/", 'md_cb_image', $text);

        // Unified Markdown links: [label](target) or [label](target "title") / [label](target 'title')
        if (!function_exists('md_cb_mdlink')) {
            function md_cb_mdlink($m) {
                $label = trim($m[1]);
                $target = isset($m[2]) ? trim($m[2]) : '';
                $title = '';
                if (isset($m[3]) && $m[3] !== '') { $title = $m[3]; }
                else if (isset($m[4]) && $m[4] !== '') { $title = $m[4]; }

                if (preg_match('/^https?:\/\//i', $target)) {
                    $url = wiki_sanitize_url($target); if ($url === '') return wiki_escape_html($m[0]);
                    $attrs = ' href="' . wiki_attr($url) . '"';
                    if ($title !== '') { $attrs .= ' title="' . wiki_attr($title) . '"'; }
                    $rel_nofollow = !isset($GLOBALS['__wiki_rel_nofollow']) || $GLOBALS['__wiki_rel_nofollow'];
                    if ($rel_nofollow) { $attrs .= ' rel="nofollow"'; }
                    return '<a' . $attrs . '>' . wiki_escape_html($label) . '</a>';
                }
                if (strlen($target) && ($target{0} === '/' || substr($target,0,2) === './' || substr($target,0,3) === '../')) {
                    $attrs = ' href="' . wiki_attr($target) . '"';
                    if ($title !== '') { $attrs .= ' title="' . wiki_attr($title) . '"'; }
                    return '<a' . $attrs . '>' . wiki_escape_html($label) . '</a>';
                }
                $href = wiki_build_internal_href($target);
                $attrs = ' href="' . wiki_attr($href) . '"';
                if ($title !== '') { $attrs .= ' title="' . wiki_attr($title) . '"'; }
                return '<a' . $attrs . '>' . wiki_escape_html($label) . '</a>';
            }
        }
        $GLOBALS['__wiki_rel_nofollow'] = isset($options['rel_nofollow']) ? (bool)$options['rel_nofollow'] : true;
        $GLOBALS['__wiki_internal_base'] = isset($options['internal_base']) ? $options['internal_base'] : 'wiki.php';
        $GLOBALS['__wiki_internal_param'] = isset($options['internal_param']) ? $options['internal_param'] : 'title';
        $text = preg_replace_callback("/\[([^\]]+)\]\(([^\s\)]+)(?:\s+\"([^\"]*)\"|\s+'([^']*)')?\)/", 'md_cb_mdlink', $text);

        // Wiki-style internal links: [[Page]] or [[Page|Label]]
        if (!function_exists('md_cb_internal_link')) {
            function md_cb_internal_link($m) {
                $page = trim($m[1]); $label = isset($m[2]) ? trim($m[2]) : $page;
                if (preg_match('/^https?:\/\//i', $page)) return wiki_escape_html($m[0]);
                $href = wiki_build_internal_href($page);
                return '<a href="' . wiki_attr($href) . '">' . wiki_escape_html($label) . '</a>';
            }
        }
        $text = preg_replace_callback('/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/', 'md_cb_internal_link', $text);

        // Footnote reference: [^id]
        if (!function_exists('md_cb_footnote_ref')) {
            function md_cb_footnote_ref($m) {
                $fid = trim($m[1]);
                if (!isset($GLOBALS['__md_footnote_order'][$fid])) {
                    $GLOBALS['__md_footnote_seq'] = isset($GLOBALS['__md_footnote_seq']) ? (int)$GLOBALS['__md_footnote_seq'] : 0;
                    $GLOBALS['__md_footnote_seq']++;
                    $GLOBALS['__md_footnote_order'][$fid] = $GLOBALS['__md_footnote_seq'];
                }
                $num = $GLOBALS['__md_footnote_order'][$fid];
                return '<sup class="footnote-ref"><a href="#fn-' . wiki_attr($fid) . '" id="fnref-' . wiki_attr($fid) . '">' . (int)$num . '</a></sup>';
            }
        }
        $text = preg_replace_callback('/\[\^([^\]]+)\]/', 'md_cb_footnote_ref', $text);

        // Emphasis
        $text = preg_replace('/\*\*([^*]+)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*([^*]+)\*/s', '<em>$1</em>', $text);

        // Auto-link bare URLs (optional)
        if (isset($options['auto_link']) && $options['auto_link']) {
            if (!function_exists('md_cb_autolink')) { function md_cb_autolink($m) { $lead = isset($m[1]) ? $m[1] : ''; $url = wiki_sanitize_url($m[2]); if ($url === '') return $m[0]; $attrs = ' href="' . wiki_attr($url) . '"'; $rel_nofollow = !isset($GLOBALS['__wiki_rel_nofollow']) || $GLOBALS['__wiki_rel_nofollow']; if ($rel_nofollow) { $attrs .= ' rel="nofollow"'; } return $lead . '<a' . $attrs . '>' . wiki_escape_html($url) . '</a>'; } }
            $text = preg_replace_callback('/(^|[^=\"\'])(https?:\/\/[\w\-\.\~\/%\?#@!$&\'"()*+,;=:\[\]]+)/', 'md_cb_autolink', $text);
        }

        // Escape remaining non-tag text
        $parts = preg_split('/(<[^>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $rebuilt = '';
        for ($i=0; $i<count($parts); $i++) { $part = $parts[$i]; if ($part === '') continue; if ($part{0} === '<') $rebuilt .= $part; else $rebuilt .= wiki_escape_html($part); }
        return $rebuilt;
    }
}

// Markdown table helpers
if (!function_exists('md_maybe_table_row')) {
    function md_maybe_table_row($line) { if (strpos($line, '|') === false) return false; if (trim($line) === '```') return false; return true; }
}
if (!function_exists('md_is_table_delim_line')) {
    function md_is_table_delim_line($line, &$aligns) {
        $aligns = array(); if ($line === null) return false; $t = trim($line); if ($t === '') return false;
        if ($t{0} === '|') { $t = substr($t, 1); } if ($t !== '' && substr($t, -1) === '|') { $t = substr($t, 0, -1); }
        $parts = explode('|', $t); if (!count($parts)) return false; $ok_any = false;
        for ($i=0; $i<count($parts); $i++) { $p = trim($parts[$i]); if ($p === '') { $aligns[] = ''; continue; }
            $left = (strlen($p) && $p{0} === ':'); $right = (strlen($p) && substr($p, -1) === ':');
            $core = $p; if ($left) $core = substr($core,1); if ($right && $core !== '') $core = substr($core,0,-1); $core = trim($core);
            if (!preg_match('/^-{3,}$/', $core)) return false; $ok_any = true; $align = '';
            if ($left && $right) $align = 'c'; else if ($right) $align = 'r'; else if ($left) $align = 'l';
            $aligns[] = $align; }
        return $ok_any; }
}
if (!function_exists('md_table_cells')) {
    function md_table_cells($line) { $t = trim($line); if ($t === '') return array(); if ($t{0} === '|') $t = substr($t,1); if ($t !== '' && substr($t,-1) === '|') $t = substr($t,0,-1); $parts = explode('|', $t); for ($i=0; $i<count($parts); $i++) { $parts[$i] = trim($parts[$i]); } return $parts; }
}
if (!function_exists('md_align_style')) { function md_align_style($a) { if ($a === 'l') return 'text-align:left'; if ($a === 'r') return 'text-align:right'; if ($a === 'c') return 'text-align:center'; return ''; } }

// Build internal page href
if (!function_exists('wiki_build_internal_href')) {
    function wiki_build_internal_href($page) { $base = isset($GLOBALS['__wiki_internal_base']) ? $GLOBALS['__wiki_internal_base'] : 'wiki.php'; $param = isset($GLOBALS['__wiki_internal_param']) ? $GLOBALS['__wiki_internal_param'] : 'title'; $href = $base; $sep = (strpos($href, '?') !== false) ? '&' : '?'; $href .= $sep . $param . '=' . rawurlencode(trim($page)); return $href; }
}

// Build data-asset href (served via wiki.php?a=asset&path=...)
if (!function_exists('wiki_build_asset_href')) {
    function wiki_build_asset_href($path) {
        $base = isset($GLOBALS['__wiki_internal_base']) ? $GLOBALS['__wiki_internal_base'] : 'wiki.php';
        $href = $base;
        $sep = (strpos($href, '?') !== false) ? '&' : '?';
        // Encode full path for query safety (slashes allowed as %2F)
        $p = rawurlencode($path);
        return $href . $sep . 'a=asset&path=' . $p;
    }
}

// Unified render entry
if (!function_exists('wiki_render')) { function wiki_render($text, $options = array()) { return md_parse($text, $options); } }
