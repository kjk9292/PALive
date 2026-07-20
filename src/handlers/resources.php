<?php

declare(strict_types=1);

// City resources.
function handleResourceActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'create_resource') {
        $stmt = $pdo->prepare(
            'INSERT INTO resources (title, category_id, contact_name, phone, website, details, created_by_user_id)
             VALUES (:title, :category_id, :contact_name, :phone, :website, :details, :created_by_user_id)'
        );
        $stmt->execute([
            'title' => trim((string) $_POST['title']),
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'contact_name' => trim((string) $_POST['contact_name']),
            'phone' => trim((string) $_POST['phone']),
            'website' => trim((string) $_POST['website']),
            'details' => trim((string) $_POST['details']),
            'created_by_user_id' => $user['id'],
        ]);
        flash('Resource added.');
        redirect('?page=resources');
    }

    if ($action === 'update_resource') {
        $params = [
            'title' => trim((string) $_POST['title']),
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'contact_name' => trim((string) $_POST['contact_name']),
            'phone' => trim((string) $_POST['phone']),
            'website' => trim((string) ($_POST['website'] ?? '')),
            'details' => trim((string) $_POST['details']),
            'id' => (int) $_POST['resource_id'],
        ];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare(
            'UPDATE resources SET title = :title, category_id = :category_id, contact_name = :contact_name,
                phone = :phone, website = :website, details = :details
             WHERE ' . $where
        );
        $stmt->execute($params);
        flash('Resource updated.');
        redirect('?page=resources');
    }

    if ($action === 'delete_resource') {
        $params = ['id' => (int) $_POST['resource_id']];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare('DELETE FROM resources WHERE ' . $where);
        $stmt->execute($params);
        flash('Resource deleted.');
        redirect('?page=resources');
    }
}
