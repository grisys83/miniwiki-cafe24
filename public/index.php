<?php
// Smart entrypoint: redirect based on login status
require_once(dirname(__FILE__) . '/../src/wiki_engine.php');

// Initialize users file
wiki_engine_init_users();

// Check login status and redirect appropriately
if (wiki_engine_is_logged_in()) {
    // User is logged in, go to front page
    header('Location: wiki.php?a=front');
} else {
    // User not logged in, go to login page
    header('Location: wiki.php?a=login');
}
exit;
?>
