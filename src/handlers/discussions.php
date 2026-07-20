<?php

declare(strict_types=1);

// Discussions and replies.
function handleDiscussionActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'create_discussion') {
        $stmt = $pdo->prepare(
            'INSERT INTO discussions (title, category_id, body, created_by_user_id, team_id)
             VALUES (:title, :category_id, :body, :created_by_user_id, :team_id)'
        );
        $teamId = !empty($_POST['team_id']) ? (int) $_POST['team_id'] : null;
        $stmt->execute([
            'title' => trim((string) $_POST['title']),
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'body' => trim((string) $_POST['body']),
            'created_by_user_id' => $user['id'],
            'team_id' => $teamId,
        ]);
        createNotification($pdo, (int) $user['id'], 'discussion', 'Your thread is live.', '?page=discussions');
        flash('Discussion created.');
        redirect('?page=discussions');
    }

    if ($action === 'reply_discussion') {
        $stmt = $pdo->prepare(
            'INSERT INTO discussion_replies (discussion_id, user_id, body)
             VALUES (:discussion_id, :user_id, :body)'
        );
        $stmt->execute([
            'discussion_id' => (int) $_POST['discussion_id'],
            'user_id' => $user['id'],
            'body' => trim((string) $_POST['body']),
        ]);
        flash('Reply posted.');
        redirect('?page=discussions');
    }

    if ($action === 'update_discussion') {
        $params = [
            'title' => trim((string) $_POST['title']),
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'body' => trim((string) $_POST['body']),
            'team_id' => !empty($_POST['team_id']) ? (int) $_POST['team_id'] : null,
            'id' => (int) $_POST['discussion_id'],
        ];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare(
            'UPDATE discussions SET title = :title, category_id = :category_id, body = :body, team_id = :team_id
             WHERE ' . $where
        );
        $stmt->execute($params);
        flash('Discussion updated.');
        redirect('?page=discussions');
    }

    if ($action === 'delete_discussion') {
        $params = ['id' => (int) $_POST['discussion_id']];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare('DELETE FROM discussions WHERE ' . $where);
        $stmt->execute($params);
        flash('Discussion deleted.');
        redirect('?page=discussions');
    }

    if ($action === 'delete_reply') {
        $params = ['id' => (int) $_POST['reply_id']];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare('DELETE FROM discussion_replies WHERE ' . $where);
        $stmt->execute($params);
        flash('Reply deleted.');
        redirect('?page=discussions');
    }
}
