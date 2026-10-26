<?php

function getFogoDatabase(): PDO {
    $dbFile = __DIR__ . '/../fogo.db';
    $schemaFile = __DIR__ . '/schema.sql';
    $firstRun = !file_exists($dbFile);

    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Ativa suporte à integridade de chaves estrangeiras
    $pdo->exec('PRAGMA foreign_keys = ON;');

    if ($firstRun && file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
    }

    return $pdo;
}