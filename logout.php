<?php
require_once __DIR__ . '/config/app.php';
(new Auth())->logout();
redirect(APP_URL . '/login.php');
