<?php
session_start();
session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');
echo "Sesión destruida. <a href='../login.php'>Ir al Login</a>";
?>
