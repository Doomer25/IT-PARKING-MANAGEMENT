<?php
// src/config.php
return [
  'db' => [
    'dsn' => 'mysql:host=127.0.0.1;dbname=parking_db;charset=utf8mb4',
    'user' => 'root',     // change if your DB user is different
    'pass' => '',         // change to your DB password
    'options' => [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
  ],
];
