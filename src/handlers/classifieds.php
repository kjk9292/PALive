<?php

declare(strict_types=1);

// Classifieds.
function handleClassifiedActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'create_classified') {
        $stmt = $pdo->prepare(
            'INSERT INTO classifieds (title, listing_type, price, neighborhood, description, expires_at, created_by_user_id)
             VALUES (:title, :listing_type, :price, :neighborhood, :description, :expires_at, :created_by_user_id)'
        );
        $price = trim((string) ($_POST['price'] ?? ''));
        $stmt->execute([
            'title' => trim((string) $_POST['title']),
            'listing_type' => in_array($_POST['listing_type'] ?? '', ['item', 'service', 'job', 'housing'], true) ? $_POST['listing_type'] : 'item',
            'price' => $price === '' ? null : (float) $price,
            'neighborhood' => trim((string) ($_POST['neighborhood'] ?? '')),
            'description' => trim((string) $_POST['description']),
            'expires_at' => normalizeDateTimeLocal((string) $_POST['expires_at']),
            'created_by_user_id' => $user['id'],
        ]);
        createNotification($pdo, (int) $user['id'], 'classified', 'Your classified listing is live.', '?page=marketplace');
        flash('Classified created.');
        redirect('?page=marketplace');
    }

    if ($action === 'update_classified') {
        $price = trim((string) ($_POST['price'] ?? ''));
        $params = [
            'title' => trim((string) $_POST['title']),
            'listing_type' => in_array($_POST['listing_type'] ?? '', ['item', 'service', 'job', 'housing'], true) ? $_POST['listing_type'] : 'item',
            'price' => $price === '' ? null : (float) $price,
            'neighborhood' => trim((string) ($_POST['neighborhood'] ?? '')),
            'description' => trim((string) $_POST['description']),
            'expires_at' => normalizeDateTimeLocal((string) $_POST['expires_at']),
            'id' => (int) $_POST['classified_id'],
        ];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare(
            'UPDATE classifieds SET title = :title, listing_type = :listing_type, price = :price,
                neighborhood = :neighborhood, description = :description, expires_at = :expires_at
             WHERE ' . $where
        );
        $stmt->execute($params);
        flash('Classified updated.');
        redirect('?page=marketplace');
    }

    if ($action === 'delete_classified') {
        $params = ['id' => (int) $_POST['classified_id']];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare('DELETE FROM classifieds WHERE ' . $where);
        $stmt->execute($params);
        flash('Classified deleted.');
        redirect('?page=marketplace');
    }
}
