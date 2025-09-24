<?php
// Smart entrypoint: redirect based on login status
require_once(dirname(__FILE__) . '/../src/wiki_engine.php');

// Toggle to match wiki.php (default: no login; rely on .htaccess IP allowlist)
if (!defined('WIKI_REQUIRE_LOGIN')) { define('WIKI_REQUIRE_LOGIN', false); }

// Initialize users file
wiki_engine_init_users();

// Check login status and redirect appropriately
if (!WIKI_REQUIRE_LOGIN) {
    header('Location: wiki.php?a=front');
} else {
    if (wiki_engine_is_logged_in()) {
        header('Location: wiki.php?a=front');
    } else {
        header('Location: wiki.php?a=login');
    }
}
exit;
?>
