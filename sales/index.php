<?php
require_once '../config/db.php';
requireLogin();
if (!defined('PAGE_TITLE'))    define('PAGE_TITLE', 'Sales History');
if (!defined('PAGE_SUBTITLE')) define('PAGE_SUBTITLE', 'All transactions');
