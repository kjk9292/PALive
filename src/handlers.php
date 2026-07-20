<?php

declare(strict_types=1);

// POST dispatch. Each domain's action handlers live in src/handlers/<domain>.php
// and are no-ops unless $action matches one they own (a match redirects + exits).
require __DIR__ . '/handlers/auth.php';
require __DIR__ . '/handlers/users.php';
require __DIR__ . '/handlers/teams.php';
require __DIR__ . '/handlers/events.php';
require __DIR__ . '/handlers/resources.php';
require __DIR__ . '/handlers/schools.php';
require __DIR__ . '/handlers/deals.php';
require __DIR__ . '/handlers/classifieds.php';
require __DIR__ . '/handlers/discussions.php';
require __DIR__ . '/handlers/messages.php';
require __DIR__ . '/handlers/news.php';
require __DIR__ . '/handlers/lookups.php';

function handlePost(PDO $pdo): void
{
    $action = $_POST['action'] ?? '';

    // Public actions that must run before a session is required.
    handleAuthActions($pdo, $action);

    // Everything past here requires a signed-in user.
    $user = requireUser($pdo);

    handleUserActions($pdo, $user, $action);
    handleTeamActions($pdo, $user, $action);
    handleEventActions($pdo, $user, $action);
    handleResourceActions($pdo, $user, $action);
    handleSchoolActions($pdo, $user, $action);
    handleDealActions($pdo, $user, $action);
    handleClassifiedActions($pdo, $user, $action);
    handleDiscussionActions($pdo, $user, $action);
    handleMessageActions($pdo, $user, $action);
    handleNewsActions($pdo, $user, $action);
    handleLookupActions($pdo, $user, $action);
}
