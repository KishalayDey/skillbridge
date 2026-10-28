<?php
session_start();

// remove all the session variables and close the session
session_unset();
session_destroy();

// The "remember_email" cookie is not deleted here, so the login page
// can still fill the email box the next time.

header("Location: index.php");
exit();
?>
