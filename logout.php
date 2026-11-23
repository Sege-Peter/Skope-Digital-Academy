<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// auth.php has already started the session
logoutUser();

// Redirect to login with logout message
header('Location: login?msg=logout_success');
exit;
