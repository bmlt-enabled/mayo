<?php

require 'vendor/autoload.php';

foreach (glob(__DIR__ . '/migrations/*.php') as $filename) {
    require_once $filename; // We'll need this since filenames don't match class names
    
    // Extract the class name by removing the timestamp prefix
    $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($filename, '.php'));
    // Convert to StudlyCase
    $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $className)));
    
    $fullyQualifiedClassName = "Mayo\\Migrations\\$className";
    $migration = new $fullyQualifiedClassName();

    if ($argv[1] === 'migrate') {
        echo "Running migration: $className\n";
        $migration->up();
    } elseif ($argv[1] === 'rollback') {
        echo "Rolling back migration: $className\n";
        $migration->down();
    }
}