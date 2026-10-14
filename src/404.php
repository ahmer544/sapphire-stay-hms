<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$adminPage = null;
foreach ($_SERVER as $value) {
    if (!is_string($value)) {
        continue;
    }

    if (preg_match('#/admin/(guests|rooms|payments|checkin|invoices|invoice-print)\.php(?:\?|$)#', $value, $matches)) {
        $adminPage = $matches[1];
        break;
    }
}

if ($adminPage !== null) {
    $params = $_GET;
    unset($params['page']);
    header('Location: ' . adminUrl($adminPage, $params));
    exit;
}

redirect('index.php');