<?php

declare(strict_types=1);

// Local deals.
function handleDealActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'create_deal') {
        $stmt = $pdo->prepare(
            'INSERT INTO deals (title, business_name, category_id, website, description, expires_at, created_by_user_id)
             VALUES (:title, :business_name, :category_id, :website, :description, :expires_at, :created_by_user_id)'
        );
        $stmt->execute([
            'title' => trim((string) $_POST['title']),
            'business_name' => trim((string) $_POST['business_name']),
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'website' => normalizeOptionalUrl($_POST['website'] ?? null) ?? '',
            'description' => trim((string) $_POST['description']),
            'expires_at' => normalizeDateTimeLocal((string) $_POST['expires_at']),
            'created_by_user_id' => $user['id'],
        ]);
        createNotification($pdo, (int) $user['id'], 'deal', 'Your deal was published.', '?page=deals');
        flash('Deal created.');
        redirect('?page=deals');
    }

    if ($action === 'update_deal') {
        $params = [
            'title' => trim((string) $_POST['title']),
            'business_name' => trim((string) $_POST['business_name']),
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'website' => normalizeOptionalUrl($_POST['website'] ?? null) ?? '',
            'description' => trim((string) $_POST['description']),
            'expires_at' => normalizeDateTimeLocal((string) $_POST['expires_at']),
            'id' => (int) $_POST['deal_id'],
        ];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare(
            'UPDATE deals SET title = :title, business_name = :business_name, category_id = :category_id,
                website = :website, description = :description, expires_at = :expires_at
             WHERE ' . $where
        );
        $stmt->execute($params);
        flash('Deal updated.');
        redirect('?page=deals');
    }

    if ($action === 'delete_deal') {
        $params = ['id' => (int) $_POST['deal_id']];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare('DELETE FROM deals WHERE ' . $where);
        $stmt->execute($params);
        flash('Deal deleted.');
        redirect('?page=deals');
    }
}
