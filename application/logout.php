<?php
declare(strict_types=1);

// Destroys the active session and redirects the user back to the login page.

session_start();
session_destroy();
header("Location: index.php?logout=1");
exit();
?>
