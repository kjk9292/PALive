<?php

declare(strict_types=1);

// Shared view context: the data every page needs regardless of route — the
// signed-in user, the visitor-log entry, sidebar stats, the one-shot flash
// message, and the team/user option lists used by the various forms.
// Expects $pdo and $page to already be set (see index.php / src/request.php).

$currentUser = currentUser($pdo);
$visitorToken = ensureVisitorToken();
logVisitor($pdo, $visitorToken, $page, $currentUser);
$stats = loadStats($pdo, $currentUser);
$flash = pullFlash();

$teamOptions = fetchAll($pdo, 'SELECT id, name FROM teams ORDER BY name');
$users = fetchAll($pdo, 'SELECT id, name, email, role FROM users ORDER BY name');
