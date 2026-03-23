<?php
/**
 * auth/logout.php
 * Destroys session and redirects to login.
 */
require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
logout_user('/modules/auth/login.php');