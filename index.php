<?php
require_once __DIR__ . '/config/app.php';
requireLogin();
redirect(APP_URL . '/modules/dashboard/index.php');
