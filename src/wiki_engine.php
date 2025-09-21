<?php
// PHP4-compatible minimal wiki engine utilities (file-based storage)

if (!defined('WIKI_DATA_DIR')) {
    define('WIKI_DATA_DIR', dirname(__FILE__) . '/../data/pages');
}
if (!defined('WIKI_HISTORY_DIR')) {
    define('WIKI_HISTORY_DIR', dirname(__FILE__) . '/../data/history');
}
if (!defined('WIKI_RENDERER')) {
    // 'markdown_wiki' (default) or 'wiki'
    define('WIKI_RENDERER', 'markdown_wiki');
}
if (!defined('WIKI_USERS_FILE')) {
    define('WIKI_USERS_FILE', dirname(__FILE__) . '/../data/users.json');
}
if (!defined('WIKI_SESSION_TIMEOUT')) {
    // 3 hours = 10800 seconds
    define('WIKI_SESSION_TIMEOUT', 10800);
}

// ---------------- User/Auth (session-based) ----------------
// users file: data/users.json (e.g. {"admin":{"password_hash":"<md5>","created":"YYYY-mm-dd HH:ii:ss"}})

if (!function_exists('wiki_engine_users_file_path')) {
    function wiki_engine_users_file_path() {
        return WIKI_USERS_FILE;
    }
}

if (!function_exists('wiki_engine_init_users')) {
    function wiki_engine_init_users() {
        $path = wiki_engine_users_file_path();
        $dir = dirname($path);
        if (!is_dir($dir)) { wiki_engine_mkdir_p($dir); }
        if (!file_exists($path) || filesize($path) === 0) {
            $default = array(
                'admin' => array(
                    // default: admin / passw0rd
                    'password_hash' => 'bed128365216c019988915ed3add75fb',
                    'created' => date('Y-m-d H:i:s')
                )
            );
            wiki_engine_write_users($default);
        }
    }
}

if (!function_exists('wiki_engine_readfile_all')) {
    function wiki_engine_readfile_all($file) {
        $fp = @fopen($file, 'rb'); if (!$fp) return '';
        $data = '';
        while (!feof($fp)) { $data .= fread($fp, 8192); }
        fclose($fp);
        return $data;
    }
}

if (!function_exists('wiki_engine_read_users')) {
    function wiki_engine_read_users() {
        $path = wiki_engine_users_file_path();
        if (!file_exists($path)) { return array(); }
        $raw = wiki_engine_readfile_all($path);
        $raw_trim = trim($raw);
        $out = array();
        // Prefer JSON if available
        if (function_exists('json_decode')) {
            $data = @json_decode($raw_trim, true);
            if (is_array($data)) {
                // Accept either {"username":{"password_hash":".."}} or {"users":[{"username":"..","password_hash":".."}]}
                if (isset($data['users']) && is_array($data['users'])) {
                    for ($i=0; $i<count($data['users']); $i++) {
                        $u = $data['users'][$i];
                        if (isset($u['username']) && isset($u['password_hash'])) {
                            $out[(string)$u['username']] = array('password_hash' => (string)$u['password_hash']);
                        }
                    }
                } else {
                    // assume map: username => info
                    foreach ($data as $name => $info) {
                        if (is_array($info) && isset($info['password_hash'])) {
                            $out[(string)$name] = array('password_hash' => (string)$info['password_hash']);
                        }
                    }
                }
                return $out;
            }
            // Safe JSON-ish extraction (PHP4 PCRE friendly)
            if (preg_match_all('/"([^"\\]+)"\s*:\s*\{([^}]*)\}/s', $raw_trim, $m2)) {
                for ($i2 = 0; $i2 < count($m2[1]); $i2++) {
                    $uname = $m2[1][$i2];
                    $inner = $m2[2][$i2];
                    if (preg_match('/\"password_hash\"\s*:\s*\"([a-fA-F0-9]{32})\"/s', $inner, $mh2)) {
                        $out[$uname] = array('password_hash' => strtolower($mh2[1]));
                    } elseif (preg_match('/"password_hash"\s*:\s*"([a-fA-F0-9]{32})"/s', $inner, $mh3)) {
                        $out[$uname] = array('password_hash' => strtolower($mh3[1]));
                    }
                }
                if (count($out)) { return $out; }
            }
        }
        // Fallback lightweight parser for {"user":{"password_hash":"<md5>"}, ...}
        if (strlen($raw_trim) && $raw_trim{0} === '{') {
            if (preg_match_all('/"([^"\\]+)"\s*:\s*\{([^}]*)\}/s', $raw_trim, $m)) {
                for ($i=0; $i<count($m[1]); $i++) {
                    $uname = $m[1][$i];
                    $inner = $m[2][$i];
                    if (preg_match('/"password_hash"\s*:\s*"([a-fA-F0-9]{32})"/s', $inner, $mh)) {
                        $out[$uname] = array('password_hash' => strtolower($mh[1]));
                    }
                }
                if (count($out)) { return $out; }
            }
            // Ultra-simple non-regex scan (PHP4 safest)
            $s = $raw_trim; $len = strlen($s); $pos = 0;
            while ($pos < $len) {
                $q1 = strpos($s, '"', $pos); if ($q1 === false) break;
                $q2 = strpos($s, '"', $q1+1); if ($q2 === false) break;
                $name = substr($s, $q1+1, $q2-$q1-1);
                $colon = strpos($s, ':', $q2+1); if ($colon === false) { $pos = $q2+1; continue; }
                $b1 = strpos($s, '{', $colon+1); if ($b1 === false) { $pos = $colon+1; continue; }
                $b2 = strpos($s, '}', $b1+1); if ($b2 === false) break;
                $inner = substr($s, $b1+1, $b2-$b1-1);
                $k = '"password_hash"';
                $kp = strpos($inner, $k);
                if ($kp !== false) {
                    $v1 = strpos($inner, '"', $kp+strlen($k));
                    if ($v1 !== false) {
                        $v2 = strpos($inner, '"', $v1+1);
                        if ($v2 !== false) {
                            $val = substr($inner, $v1+1, $v2-$v1-1);
                            if (preg_match('/^[A-Fa-f0-9]{32}$/', $val)) { $out[$name] = array('password_hash' => strtolower($val)); }
                        }
                    }
                }
                $pos = $b2 + 1;
            }
            if (count($out)) { return $out; }
        }
        // Line-based fallback: username:md5
        $lines = explode("\n", $raw);
        for ($i=0; $i<count($lines); $i++) {
            $line = trim($lines[$i]); if ($line === '' || $line{0} === '#') continue;
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]); $hash = strtolower(trim($parts[1]));
                if ($name !== '' && preg_match('/^[a-f0-9]{32}$/', $hash)) { $out[$name] = array('password_hash' => $hash); }
            }
        }
        return $out;
    }
}

if (!function_exists('wiki_engine_write_users')) {
    function wiki_engine_write_users($users_map) {
        $path = wiki_engine_users_file_path();
        $dir = dirname($path);
        if (!is_dir($dir)) { wiki_engine_mkdir_p($dir); }
        // Normalize to map format {"user":{"password_hash":"..."}}
        $obj = array();
        foreach ($users_map as $name => $info) {
            $hash = isset($info['password_hash']) ? (string)$info['password_hash'] : '';
            $created = isset($info['created']) ? (string)$info['created'] : date('Y-m-d H:i:s');
            $obj[(string)$name] = array('password_hash' => $hash, 'created' => $created);
        }
        if (function_exists('json_encode')) {
            $json = json_encode($obj);
        } else {
            // Minimal JSON builder
            $parts = array();
            foreach ($obj as $name => $info) {
                $parts[] = '"' . addslashes($name) . '":{' . '"password_hash":"' . addslashes($info['password_hash']) . '","created":"' . addslashes($info['created']) . '"}';
            }
            $json = '{' . implode(',', $parts) . '}';
        }
        return wiki_engine_write_file($path, $json);
    }
}

if (!function_exists('wiki_engine_list_users')) {
    function wiki_engine_list_users() {
        $u = wiki_engine_read_users();
        $names = array();
        foreach ($u as $name => $info) { $names[] = (string)$name; }
        sort($names);
        return $names;
    }
}

if (!function_exists('wiki_engine_is_admin')) {
    function wiki_engine_is_admin($username = null) {
        if ($username === null || $username === '') {
            $username = wiki_engine_get_current_user();
        }
        // Simple rule: username 'admin' is the admin.
        return ($username === 'admin');
    }
}

if (!function_exists('wiki_engine_set_password')) {
    function wiki_engine_set_password($username, $new_password, &$error) {
        $error = '';
        $username = trim((string)$username);
        if ($username === '') { $error = 'Username required'; return false; }
        if ($new_password === '') { $error = 'Password required'; return false; }
        $users = wiki_engine_read_users();
        if (!isset($users[$username])) { $error = 'User not found'; return false; }
        $users[$username]['password_hash'] = strtolower(md5($new_password));
        if (!isset($users[$username]['created'])) { $users[$username]['created'] = date('Y-m-d H:i:s'); }
        return wiki_engine_write_users($users);
    }
}

if (!function_exists('wiki_engine_change_password_self')) {
    function wiki_engine_change_password_self($old_password, $new_password, &$error) {
        $error = '';
        $username = wiki_engine_get_current_user();
        if (!wiki_engine_verify_login($username, $old_password)) { $error = 'Current password incorrect'; return false; }
        return wiki_engine_set_password($username, $new_password, $error);
    }
}

if (!function_exists('wiki_engine_add_user')) {
    function wiki_engine_add_user($username, $password, &$error) {
        $error = '';
        $username = trim((string)$username);
        if ($username === '') { $error = 'Username required'; return false; }
        if (!preg_match('/^[A-Za-z0-9_\-\.]{1,32}$/', $username)) { $error = 'Invalid username'; return false; }
        $password = (string)$password;
        if ($password === '') { $error = 'Password required'; return false; }
        $users = wiki_engine_read_users();
        if (isset($users[$username])) { $error = 'User already exists'; return false; }
        $users[$username] = array('password_hash' => strtolower(md5($password)), 'created' => date('Y-m-d H:i:s'));
        return wiki_engine_write_users($users);
    }
}

if (!function_exists('wiki_engine_delete_user')) {
    function wiki_engine_delete_user($username, &$error) {
        $error = '';
        $username = trim((string)$username);
        if ($username === '') { $error = 'Username required'; return false; }
        if ($username === 'admin') { $error = 'Cannot delete admin user'; return false; }
        $current = wiki_engine_get_current_user();
        if ($current === $username) { $error = 'Cannot delete current logged-in user'; return false; }
        $users = wiki_engine_read_users();
        if (!isset($users[$username])) { $error = 'User not found'; return false; }
        unset($users[$username]);
        return wiki_engine_write_users($users);
    }
}

if (!function_exists('wiki_engine_verify_login')) {
    function wiki_engine_verify_login($username, $password) {
        $username = trim((string)$username);
        $password = (string)$password;
        if ($username === '') return false;
        $users = wiki_engine_read_users();
        if (!isset($users[$username]) || !isset($users[$username]['password_hash'])) return false;
        $hash = strtolower($users[$username]['password_hash']);
        $try = strtolower(md5($password));
        return ($hash === $try);
    }
}

if (!function_exists('wiki_engine_login')) {
    function wiki_engine_login($username) {
        if (session_id() === '') { @session_start(); }
        if (!isset($_SESSION)) { $_SESSION = array(); }
        $_SESSION['wiki_user'] = (string)$username;
        $_SESSION['wiki_last_active'] = time();
    }
}

if (!function_exists('wiki_engine_logout')) {
    function wiki_engine_logout() {
        if (session_id() === '') { @session_start(); }
        if (!isset($_SESSION)) { $_SESSION = array(); }
        unset($_SESSION['wiki_user']);
        unset($_SESSION['wiki_last_active']);
    }
}

if (!function_exists('wiki_engine_is_logged_in')) {
    function wiki_engine_is_logged_in() {
        if (session_id() === '') { @session_start(); }
        if (!isset($_SESSION)) { $_SESSION = array(); }
        if (!isset($_SESSION['wiki_user'])) return false;
        $last = isset($_SESSION['wiki_last_active']) ? (int)$_SESSION['wiki_last_active'] : 0;
        $now = time();
        if ($last > 0 && ($now - $last) > WIKI_SESSION_TIMEOUT) {
            // expired
            wiki_engine_logout();
            return false;
        }
        $_SESSION['wiki_last_active'] = $now;
        return true;
    }
}

if (!function_exists('wiki_engine_get_current_user')) {
    function wiki_engine_get_current_user() {
        if (session_id() === '') { @session_start(); }
        if (!isset($_SESSION)) { $_SESSION = array(); }
        return isset($_SESSION['wiki_user']) ? (string)$_SESSION['wiki_user'] : 'guest';
    }
}

if (!function_exists('wiki_engine_mkdir_p')) {
    function wiki_engine_mkdir_p($path, $mode = 0775) {
        if (is_dir($path)) return true;
        $p = str_replace('\\', '/', $path);
        $bits = explode('/', $p);
        $cur = '';
        // Handle absolute path
        if (substr($p, 0, 1) === '/') { $cur = '/'; }
        // Handle Windows drive letter like C:/
        if (preg_match('/^[A-Za-z]:\//', $p)) {
            $cur = substr($p, 0, 3); // e.g., C:/
            $bits = explode('/', substr($p, 3));
        }
        for ($i = 0; $i < count($bits); $i++) {
            $seg = $bits[$i];
            if ($seg === '' || $seg === '.') continue;
            if ($cur === '' || $cur === '/') { $cur .= $seg; }
            else { $cur .= '/' . $seg; }
            if (!is_dir($cur)) { @mkdir($cur, $mode); }
        }
        return is_dir($path);
    }
}

if (!function_exists('wiki_engine_safe_title')) {
    function wiki_engine_safe_title($title) {
        // Normalize title: trim, collapse whitespace, disallow path traversal and control chars
        $title = trim($title);
        // Remove nulls and control chars
        $title = preg_replace('/[\x00-\x1F\x7F]/', '', $title);
        // Disallow slashes and backslashes
        $title = str_replace(array('/', '\\'), ' ', $title);
        // Collapse spaces
        $title = preg_replace('/\s+/', ' ', $title);
        if ($title === '') { $title = 'Home'; }
        return $title;
    }
}

if (!function_exists('wiki_engine_filename_for_title')) {
    function wiki_engine_filename_for_title($title) {
        $safe = wiki_engine_safe_title($title);
        // Use rawurlencode to make filesystem-safe ASCII filenames
        $enc = rawurlencode($safe);
        return WIKI_DATA_DIR . '/' . $enc . '.md';
    }
}

if (!function_exists('wiki_engine_exists')) {
    function wiki_engine_exists($title) {
        $file = wiki_engine_filename_for_title($title);
        return file_exists($file);
    }
}

if (!function_exists('wiki_engine_history_dir_for_title')) {
    function wiki_engine_history_dir_for_title($title) {
        $safe = wiki_engine_safe_title($title);
        $enc = rawurlencode($safe);
        return WIKI_HISTORY_DIR . '/' . $enc;
    }
}

if (!function_exists('wiki_engine_read_page')) {
    function wiki_engine_read_page($title) {
        $file = wiki_engine_filename_for_title($title);
        if (!file_exists($file)) return '';
        $fp = @fopen($file, 'rb');
        if (!$fp) return '';
        $data = '';
        while (!feof($fp)) { $data .= fread($fp, 8192); }
        fclose($fp);
        return $data;
    }
}

if (!function_exists('wiki_engine_delete_page')) {
    function wiki_engine_delete_page($title) {
        $file = wiki_engine_filename_for_title($title);
        if (!file_exists($file)) return true; // already gone
        // Save current to history with "-deleted" marker
        $old = wiki_engine_read_page($title);
        $hdir = wiki_engine_history_dir_for_title($title);
        wiki_engine_mkdir_p($hdir);
        $stamp = date('Ymd_His') . '-deleted';
        $hist_file = $hdir . '/' . $stamp . '.md';
        wiki_engine_write_file($hist_file, $old);
        // Remove current file
        return @unlink($file);
    }
}

if (!function_exists('wiki_engine_write_file')) {
    function wiki_engine_write_file($file, $content) {
        $dir = dirname($file);
        wiki_engine_mkdir_p($dir);
        $tmp = $file . '.tmp.' . mt_rand();
        $fp = @fopen($tmp, 'wb');
        if (!$fp) return false;
        if (!flock($fp, LOCK_EX)) { /* ignore if unsupported */ }
        $ok = true;
        $len = strlen($content);
        $written = 0;
        while ($written < $len) {
            $n = fwrite($fp, substr($content, $written, 8192));
            if ($n === false) { $ok = false; break; }
            $written += $n;
        }
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($ok) {
            @chmod($tmp, 0664);
            // Atomic-ish replace
            if (@rename($tmp, $file)) { return true; }
        }
        // Fallback: direct write
        $fp2 = @fopen($file, 'wb');
        if ($fp2) {
            if (!flock($fp2, LOCK_EX)) { /* ignore */ }
            fwrite($fp2, $content);
            fflush($fp2);
            flock($fp2, LOCK_UN);
            fclose($fp2);
            @chmod($file, 0664);
            @unlink($tmp);
            return true;
        }
        @unlink($tmp);
        return false;
    }
}

if (!function_exists('wiki_engine_save_page')) {
    function wiki_engine_save_page($title, $content) {
        $file = wiki_engine_filename_for_title($title);
        // Save current version to history if exists
        if (file_exists($file)) {
            $old = wiki_engine_read_page($title);
            $hdir = wiki_engine_history_dir_for_title($title);
            wiki_engine_mkdir_p($hdir);
            $stamp = date('Ymd_His');
            $hist_file = $hdir . '/' . $stamp . '.md';
            wiki_engine_write_file($hist_file, $old);
        }
        return wiki_engine_write_file($file, $content);
    }
}

if (!function_exists('wiki_engine_check_password')) {
    function wiki_engine_check_password($pw) {
        // Constant-time not necessary here; this is a simple legacy feature.
        return ($pw === WIKI_EDIT_PASSWORD);
    }
}

// Handle legacy magic_quotes_gpc input escaping on PHP 4
if (!function_exists('wiki_engine_unmagic')) {
    function wiki_engine_unmagic($value) {
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            if (is_array($value)) {
                $out = array();
                foreach ($value as $k => $v) { $out[$k] = wiki_engine_unmagic($v); }
                return $out;
            } else {
                return stripslashes($value);
            }
        }
        return $value;
    }
}

if (!function_exists('wiki_engine_is_markdown_mode')) {
    function wiki_engine_is_markdown_mode() {
        return (defined('WIKI_RENDERER') && constant('WIKI_RENDERER') === 'markdown_wiki');
    }
}

// Remove unnecessary backslashes before quotes in Markdown mode (outside code blocks/inline code)
if (!function_exists('wiki_engine_cleanup_quotes_for_markdown')) {
    function wiki_engine_cleanup_quotes_for_markdown($text) {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $lines = explode("\n", $text);
        $out = '';
        $in_fence = false;
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $trim = trim($line);
            if ($trim === '```') { $in_fence = !$in_fence; $out .= $line . "\n"; continue; }
            if ($in_fence) { $out .= $line . "\n"; continue; }
            // Process inline, skipping regions in backticks
            $out .= wiki_engine_cleanup_line_quotes_md($line) . "\n";
        }
        return $out;
    }
}

if (!function_exists('wiki_engine_cleanup_line_quotes_md')) {
    function wiki_engine_cleanup_line_quotes_md($line) {
        $len = strlen($line);
        $out = '';
        $i = 0;
        $in_inline = false;
        while ($i < $len) {
            $ch = $line{$i};
            if ($ch === '`') { $in_inline = !$in_inline; $out .= $ch; $i++; continue; }
            if (!$in_inline && $ch === '\\') {
                // count consecutive backslashes
                $j = $i;
                while ($j < $len && $line{$j} === '\\') { $j++; }
                $count = $j - $i;
                $next = ($j < $len) ? $line{$j} : '';
                if ($next === '\'' || $next === '"') {
                    // keep even number of backslashes, drop one if odd
                    $keep = ($count >> 1); // floor(count/2)
                    if ($keep > 0) { $out .= str_repeat('\\', $keep); }
                    $out .= $next;
                    $i = $j + 1;
                    continue;
                } else {
                    $out .= str_repeat('\\', $count);
                    $i = $j;
                    continue;
                }
            }
            $out .= $ch; $i++;
        }
        return $out;
    }
}

// --- Rename (Move) Page and Update Links ---
if (!function_exists('wiki_engine_rename_or_copy')) {
    function wiki_engine_rename_or_copy($old, $new) {
        if (@rename($old, $new)) return true;
        $data = '';
        $fp = @fopen($old, 'rb');
        if ($fp) {
            while (!feof($fp)) { $data .= fread($fp, 8192); }
            fclose($fp);
            if (wiki_engine_write_file($new, $data)) {
                @unlink($old);
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('wiki_engine_replace_links_safe')) {
    function wiki_engine_replace_links_safe($text, $old, $new) {
        // Replace [[Old]] and [[Old|Label]] outside of fenced code blocks and inline backticks.
        $lines = explode("\n", $text);
        $out = '';
        $in_tick_block = false;  // for ``` ... ```
        $re_old = preg_quote($old, '/');
        $re1 = '/\[\[\s*' . $re_old . '\s*\]\]/';
        $re2 = '/\[\[\s*' . $re_old . '\s*\|([^\]]*)\]\]/';

        $ph = array(); $ph_idx = 0;
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $trim = trim($line);
            if ($in_tick_block) { if ($trim === '```') { $in_tick_block = false; } $out .= $line . "\n"; continue; }
            if ($trim === '```') { $in_tick_block = true; $out .= $line . "\n"; continue; }

            // Protect inline code: `...`
            $protected = $line;
            if (preg_match_all('/`[^`\n]+`/', $protected, $m2)) {
                for ($k = 0; $k < count($m2[0]); $k++) {
                    $tok = $m2[0][$k]; $key = "\x1A" . $ph_idx . "\x1A"; $ph[$key] = $tok; $protected = str_replace($tok, $key, $protected); $ph_idx++;
                }
            }
            $protected = preg_replace($re1, '[[' . $new . ']]', $protected);
            $protected = preg_replace($re2, '[[' . $new . '|$1]]', $protected);
            if (count($ph)) { foreach ($ph as $key => $tok) { $protected = str_replace($key, $tok, $protected); } $ph = array(); $ph_idx = 0; }
            $out .= $protected . "\n";
        }
        return $out;
    }
}

if (!function_exists('wiki_engine_update_links')) {
    function wiki_engine_update_links($old_title, $new_title, $limit /* 0 = all */) {
        $pages = wiki_engine_list_pages();
        $old = wiki_engine_safe_title($old_title);
        $new = wiki_engine_safe_title($new_title);
        $updated = 0;
        for ($i = 0; $i < count($pages); $i++) {
            $t = $pages[$i];
            $content = wiki_engine_read_page($t);
            $orig = $content;
            $content = wiki_engine_replace_links_safe($content, $old, $new);
            if ($content !== $orig) {
                wiki_engine_save_page($t, $content);
                $updated++;
                if ($limit > 0 && $updated >= $limit) { break; }
            }
        }
        return array('updated' => $updated, 'total' => count($pages));
    }
}

if (!function_exists('wiki_engine_rename_page')) {
    function wiki_engine_rename_page($old_title, $new_title, $update_links, &$error, $leave_stub, $update_limit) {
        $error = '';
        $old = wiki_engine_safe_title($old_title);
        $new = wiki_engine_safe_title($new_title);
        if ($old === $new) { $error = 'Same title'; return false; }
        $old_file = wiki_engine_filename_for_title($old);
        $new_file = wiki_engine_filename_for_title($new);
        if (!file_exists($old_file)) { $error = 'Source page not found'; return false; }
        if (file_exists($new_file)) { $error = 'Target page already exists'; return false; }

        // Ensure dirs
        wiki_engine_mkdir_p(dirname($new_file));

        // Move page file
        if (!wiki_engine_rename_or_copy($old_file, $new_file)) {
            $error = 'Failed to move page file';
            return false;
        }

        // Move history dir if present
        $old_hist = wiki_engine_history_dir_for_title($old);
        $new_hist = wiki_engine_history_dir_for_title($new);
        if (is_dir($old_hist)) {
            if (!is_dir($new_hist)) {
                wiki_engine_mkdir_p(dirname($new_hist));
                @rename($old_hist, $new_hist);
            } else {
                // Merge: move files
                $dh = @opendir($old_hist);
                if ($dh) {
                    while (($f = readdir($dh)) !== false) {
                        if ($f === '.' || $f === '..') continue;
                        @rename($old_hist . '/' . $f, $new_hist . '/' . $f);
                    }
                    closedir($dh);
                }
                @rmdir($old_hist);
            }
        }

        if ($leave_stub) {
            // Create a redirect stub on old title
            $stub = "#REDIRECT [[" . $new . "]]\n\nThis page has moved to [[" . $new . "]].";
            wiki_engine_write_file($old_file, $stub);
        }

        if ($update_links) {
            if (!is_numeric($update_limit)) { $update_limit = 0; }
            wiki_engine_update_links($old, $new, (int)$update_limit);
        }
        return true;
    }
}

if (!function_exists('wiki_engine_list_pages')) {
    function wiki_engine_list_pages() {
        wiki_engine_mkdir_p(WIKI_DATA_DIR);
        $dh = @opendir(WIKI_DATA_DIR);
        if (!$dh) return array();
        $list = array();
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') continue;
            if (substr($f, -3) !== '.md') continue;
            $enc = substr($f, 0, strlen($f) - 3);
            $title = rawurldecode($enc);
            $list[] = $title;
        }
        closedir($dh);
        sort($list);
        return $list;
    }
}

if (!function_exists('wiki_engine_search')) {
    function wiki_engine_search($query, $limit) {
        $query = trim($query);
        if ($query === '') return array();
        $pages = wiki_engine_list_pages();
        $out = array();
        for ($i = 0; $i < count($pages); $i++) {
            $t = $pages[$i];
            $content = wiki_engine_read_page($t);
            if (stripos_compat($content, $query) !== false || stripos_compat($t, $query) !== false) {
                $out[] = $t;
                if (count($out) >= $limit) break;
            }
        }
        return $out;
    }
}

if (!function_exists('stripos_compat')) {
    function stripos_compat($haystack, $needle) {
        // Case-insensitive strpos for PHP4
        return strpos(strtolower($haystack), strtolower($needle));
    }
}

if (!function_exists('wiki_engine_list_history')) {
    function wiki_engine_list_history($title) {
        $hdir = wiki_engine_history_dir_for_title($title);
        if (!is_dir($hdir)) return array();
        $dh = @opendir($hdir);
        if (!$dh) return array();
        $list = array();
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') continue;
            if (substr($f, -3) !== '.md') continue;
            $list[] = $f; // keep stamp.txt
        }
        closedir($dh);
        rsort($list);
        return $list;
    }
}

if (!function_exists('wiki_engine_csrf_token')) {
    function wiki_engine_csrf_token() {
        if (session_id() === '') { @session_start(); }
        if (!isset($_SESSION)) { $_SESSION = array(); }
        if (!isset($_SESSION['wiki_token'])) {
            $_SESSION['wiki_token'] = md5(uniqid('wiki', true));
        }
        return $_SESSION['wiki_token'];
    }
}

if (!function_exists('wiki_engine_verify_token')) {
    function wiki_engine_verify_token($token) {
        $t = wiki_engine_csrf_token();
        return ($token === $t);
    }
}

// Sync a page's content from a file under public/ (e.g., syntaxguide.md)
// Canonical SyntaxGuide path under data/
if (!function_exists('wiki_engine_canonical_syntaxguide_path')) {
    function wiki_engine_canonical_syntaxguide_path() {
        return dirname(__FILE__) . '/../data/syntaxguide.md';
    }
}

if (!function_exists('wiki_engine_read_canonical_syntaxguide')) {
    function wiki_engine_read_canonical_syntaxguide() {
        $path = wiki_engine_canonical_syntaxguide_path();
        if (!file_exists($path)) return '';
        $fp = @fopen($path, 'rb'); if (!$fp) return '';
        $data = '';
        while (!feof($fp)) { $data .= fread($fp, 8192); }
        fclose($fp);
        return $data;
    }
}

if (!function_exists('wiki_engine_save_canonical_syntaxguide')) {
    function wiki_engine_save_canonical_syntaxguide($content) {
        $path = wiki_engine_canonical_syntaxguide_path();
        // History under SyntaxGuide title
        $hdir = wiki_engine_history_dir_for_title('SyntaxGuide');
        wiki_engine_mkdir_p($hdir);
        $stamp = date('Ymd_His');
        $hist_file = $hdir . '/' . $stamp . '.md';
        wiki_engine_write_file($hist_file, $content);
        return wiki_engine_write_file($path, $content);
    }
}

// Canonical FrontPage under data/
if (!function_exists('wiki_engine_canonical_frontpage_path')) {
    function wiki_engine_canonical_frontpage_path() {
        return dirname(__FILE__) . '/../data/frontpage.md';
    }
}
if (!function_exists('wiki_engine_read_canonical_frontpage')) {
    function wiki_engine_read_canonical_frontpage() {
        $path = wiki_engine_canonical_frontpage_path();
        if (!file_exists($path)) return '';
        $fp = @fopen($path, 'rb'); if (!$fp) return '';
        $data = '';
        while (!feof($fp)) { $data .= fread($fp, 8192); }
        fclose($fp);
        return $data;
    }
}
if (!function_exists('wiki_engine_save_canonical_frontpage')) {
    function wiki_engine_save_canonical_frontpage($content) {
        $path = wiki_engine_canonical_frontpage_path();
        $hdir = wiki_engine_history_dir_for_title('FrontPage');
        wiki_engine_mkdir_p($hdir);
        $stamp = date('Ymd_His');
        $hist_file = $hdir . '/' . $stamp . '.md';
        wiki_engine_write_file($hist_file, $content);
        return wiki_engine_write_file($path, $content);
    }
}
