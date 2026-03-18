<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

session_start();
session_destroy();

// Redirect to login with logout message
header('Location: login.php?msg=logout_success');
exit;
