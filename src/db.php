<?php
// src/db.php
$config = require __DIR__ . '/config.php';
function getPDO() {
    static $pdo = null;
    global $config;
    if ($pdo === null) {
        $db = $config['db'];
        $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], $db['options']);
    }
    return $pdo;
}
