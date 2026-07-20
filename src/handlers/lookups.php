<?php

declare(strict_types=1);

// Lookup values: event types & news categories (admin-only).
function handleLookupActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'create_lookup') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $type = ($_POST['lookup_type'] ?? '') === 'news' ? 'news' : 'event';
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label === '') {
            throw new RuntimeException('Label is required.');
        }
        $pdo->prepare('INSERT IGNORE INTO lookup_values (type, label) VALUES (:type, :label)')
            ->execute(['type' => $type, 'label' => $label]);
        flash('Category added.');
        redirect('?page=admin');
    }

    if ($action === 'update_lookup') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label === '') {
            throw new RuntimeException('Label is required.');
        }
        $pdo->prepare('UPDATE lookup_values SET label = :label WHERE id = :id')
            ->execute(['label' => $label, 'id' => (int) $_POST['lookup_id']]);
        flash('Category updated.');
        redirect('?page=admin');
    }

    if ($action === 'delete_lookup') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $pdo->prepare('DELETE FROM lookup_values WHERE id = :id')
            ->execute(['id' => (int) $_POST['lookup_id']]);
        flash('Category deleted.');
        redirect('?page=admin');
    }
}
