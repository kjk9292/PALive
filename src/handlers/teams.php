<?php

declare(strict_types=1);

// Teams.
function handleTeamActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'create_team') {
        $stmt = $pdo->prepare(
            'INSERT INTO teams (name, description, visibility, owner_user_id)
             VALUES (:name, :description, :visibility, :owner_user_id)'
        );
        $stmt->execute([
            'name' => trim((string) $_POST['name']),
            'description' => trim((string) $_POST['description']),
            'visibility' => $_POST['visibility'] === 'private' ? 'private' : 'public',
            'owner_user_id' => $user['id'],
        ]);
        $teamId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO team_members (team_id, user_id) VALUES (:team_id, :user_id)')
            ->execute(['team_id' => $teamId, 'user_id' => $user['id']]);
        createNotification($pdo, (int) $user['id'], 'team', 'Your team was created.', '?page=teams');
        flash('Team created.');
        redirect('?page=teams');
    }

    if ($action === 'update_team') {
        $params = [
            'name' => trim((string) $_POST['name']),
            'description' => trim((string) $_POST['description']),
            'visibility' => ($_POST['visibility'] ?? '') === 'private' ? 'private' : 'public',
            'id' => (int) $_POST['team_id'],
        ];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND owner_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare(
            'UPDATE teams SET name = :name, description = :description, visibility = :visibility
             WHERE ' . $where
        );
        $stmt->execute($params);
        flash('Team updated.');
        redirect('?page=teams');
    }

    if ($action === 'delete_team') {
        $params = ['id' => (int) $_POST['team_id']];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND owner_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare('DELETE FROM teams WHERE ' . $where);
        $stmt->execute($params);
        flash('Team deleted.');
        redirect('?page=teams');
    }
}
