<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Détruire la session
session_unset();
session_destroy();

// Rediriger vers la page de connexion
header("Location: index.php");
exit();
?>