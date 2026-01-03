#!/usr/bin/env php
<?php

/**
 * Script to generate OpenAPI JSON documentation
 * This script uses the NelmioApiDocBundle to generate the documentation
 */

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

// Boot the Symfony kernel
$kernel = new \App\Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? false));
$kernel->boot();

$container = $kernel->getContainer();

// Get the OpenAPI documentation generator
$generator = $container->get('nelmio_api_doc.generator.default');
$spec = $generator->generate();

// Convert to array and then to JSON
$openApiArray = $spec->toArray();
$openApiJson = json_encode($openApiArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Create docs directory if it doesn't exist
$docsDir = __DIR__ . '/../docs';
if (!is_dir($docsDir)) {
    mkdir($docsDir, 0755, true);
}

// Write to file
$outputFile = $docsDir . '/openapi.json';
file_put_contents($outputFile, $openApiJson);

echo "OpenAPI documentation generated successfully at: {$outputFile}\n";
echo "File size: " . number_format(strlen($openApiJson)) . " bytes\n";
