<?php
require_once __DIR__ . '/config/config.php';

redirect(isAuthenticated() ? 'panel/dashboard.php' : 'auth/login.php');
