<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/helpers.php';
require __DIR__ . '/src/handlers.php';

$pdo = pdo();
bootstrapSchema($pdo);

$errors = [];

// Parse the incoming request into typed page/view variables.
require __DIR__ . '/src/request.php';

// Apply any state-changing action before rendering.
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePost($pdo);
    }
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
}

// Assemble the shared view context, then render the page.
require __DIR__ . '/src/context.php';
require __DIR__ . '/views/layout.php';
