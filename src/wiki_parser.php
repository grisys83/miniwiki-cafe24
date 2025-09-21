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
        $in_blockquote = false;
        $list_stack = array(); // 'ul' or 'ol'
        $li_open = array();

        $total = count($lines);
        for ($i = 0; $i < $total; $i++) {
            $line = $lines[$i];
            $trim = rtrim($line);

            if ($in_pre) {
                if (trim($line) === '```') {
                    $out .= '<pre>' . wiki_escape_html($pre_buffer) . "</pre>\n";
                    $in_pre = false; $pre_buffer = '';
                    continue;
                } else { $pre_buffer .= $line . "\n"; continue; }
            }

            // Fenced code
            if (trim($line) === '```') {
                md_close_paragraph($out, $para_open);
                md_close_lists_all($out, $list_stack, $li_open);
                if ($in_blockquote) { $out .= "</blockquote>\n"; $in_blockquote = false; }
                $in_pre = true; $pre_buffer = '';
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

            // Lists
            if (preg_match('/^(\s*)([-+*]|\d+\.)\s+(.*)$/', $line, $ml)) {
                $indent = strlen($ml[1]);
                $marker = $ml[2];
                $content_raw = $ml[3];
                $type = (substr($marker, -1) === '.') ? 'ol' : 'ul';
                $level = (int) floor($indent / 2);
                $cur = count($list_stack);
                if ($level < $cur) {
                    for ($lvl = $cur - 1; $lvl >= $level; $lvl--) {
                        if ($li_open[$lvl]) { $out .= "</li>\n"; $li_open[$lvl] = false; }
                        $out .= '</' . $list_stack[$lvl] . ">\n";
                        array_pop($list_stack); array_pop($li_open);
                    }
                } elseif ($level > $cur) {
                    for ($lvl = $cur; $lvl < $level; $lvl++) {
                        $out .= '<ul>' . "\n"; $list_stack[] = 'ul'; $li_open[] = false;
                    }
                }
                if (!count($list_stack) || $list_stack[count($list_stack)-1] !== $type) {
                    $out .= '<' . $type . ">\n"; $list_stack[] = $type; $li_open[] = false;
                }
                $deep = count($list_stack) - 1;
                if (!$li_open[$deep]) { $out .= '<li>'; $li_open[$deep] = true; } else { $out .= "</li>\n<li>"; }
                $out .= md_inline(trim($content_raw), $options);
                $next = ($i+1<$total) ? $lines[$i+1] : '';
                if (!preg_match('/^(\s*)([-+*]|\d+\.)\s+/', $next)) { $out .= "</li>\n"; $li_open[$deep] = false; }
                continue;
            } else { if (count($list_stack)) { md_close_lists_all($out, $list_stack, $li_open); } }

            // Blank line ends paragraph
            if (trim($line) === '') { md_close_paragraph($out, $para_open); continue; }

            // Paragraph
            if (!$para_open) { $out .= '<p>'; $para_open = true; }
            else { $out .= $hard_wrap ? "<br />\n" : "\n"; }
            $out .= md_inline(trim($line), $options);
        }

        if ($in_pre) { $out .= '<pre>' . wiki_escape_html($pre_buffer) . "</pre>\n"; }
        if ($in_blockquote) { $out .= "</blockquote>\n"; }
        if (count($list_stack)) { md_close_lists_all($out, $list_stack, $li_open); }
        md_close_paragraph($out, $para_open);
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

        // Images: ![alt](url)
        if (!function_exists('md_cb_image')) {
            function md_cb_image($m) { $alt = trim($m[1]); $url = wiki_sanitize_url($m[2]); if ($url === '') return wiki_escape_html($m[0]); return '<img src="' . wiki_attr($url) . '" alt="' . wiki_attr($alt) . '" />'; }
        }
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^\)\s]+)\)/', 'md_cb_image', $text);

        // Unified Markdown links: [label](target)
        if (!function_exists('md_cb_mdlink')) {
            function md_cb_mdlink($m) {
                $label = trim($m[1]); $target = trim($m[2]);
                if (preg_match('/^https?:\/\//i', $target)) {
                    $url = wiki_sanitize_url($target); if ($url === '') return wiki_escape_html($m[0]);
                    $attrs = ' href="' . wiki_attr($url) . '"';
                    $rel_nofollow = !isset($GLOBALS['__wiki_rel_nofollow']) || $GLOBALS['__wiki_rel_nofollow'];
                    if ($rel_nofollow) { $attrs .= ' rel="nofollow"'; }
                    return '<a' . $attrs . '>' . wiki_escape_html($label) . '</a>';
                }
                if (strlen($target) && ($target{0} === '/' || substr($target,0,2) === './' || substr($target,0,3) === '../')) {
                    return '<a href="' . wiki_attr($target) . '">' . wiki_escape_html($label) . '</a>';
                }
                $href = wiki_build_internal_href($target);
                return '<a href="' . wiki_attr($href) . '">' . wiki_escape_html($label) . '</a>';
            }
        }
        $GLOBALS['__wiki_rel_nofollow'] = isset($options['rel_nofollow']) ? (bool)$options['rel_nofollow'] : true;
        $GLOBALS['__wiki_internal_base'] = isset($options['internal_base']) ? $options['internal_base'] : 'wiki.php';
        $GLOBALS['__wiki_internal_param'] = isset($options['internal_param']) ? $options['internal_param'] : 'title';
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^\)]+)\)/', 'md_cb_mdlink', $text);

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

// Unified render entry
if (!function_exists('wiki_render')) { function wiki_render($text, $options = array()) { return md_parse($text, $options); } }
