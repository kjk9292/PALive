<?php

declare(strict_types=1);

// Events + RSVPs.
function handleEventActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'create_event') {
        $stmt = $pdo->prepare(
            'INSERT INTO events (title, description, category_id, venue, address, website, starts_at, ends_at, created_by_user_id, team_id)
             VALUES (:title, :description, :category_id, :venue, :address, :website, :starts_at, :ends_at, :created_by_user_id, :team_id)'
        );
        $teamId = !empty($_POST['team_id']) ? (int) $_POST['team_id'] : null;
        $stmt->execute([
            'title' => trim((string) $_POST['title']),
            'description' => trim((string) $_POST['description']),
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'venue' => trim((string) $_POST['venue']),
            'address' => trim((string) $_POST['address']),
            'website' => normalizeOptionalUrl($_POST['website'] ?? null),
            'starts_at' => normalizeDateTimeLocal((string) $_POST['starts_at']),
            'ends_at' => normalizeDateTimeLocal((string) $_POST['ends_at']),
            'created_by_user_id' => $user['id'],
            'team_id' => $teamId,
        ]);
        createNotification($pdo, (int) $user['id'], 'event', 'Your event was published.', '?page=events');
        flash('Event created.');
        redirect('?page=events');
    }

    if ($action === 'update_event') {
        $params = [
            'title' => trim((string) $_POST['title']),
            'description' => trim((string) $_POST['description']),
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'venue' => trim((string) $_POST['venue']),
            'address' => trim((string) $_POST['address']),
            'website' => normalizeOptionalUrl($_POST['website'] ?? null),
            'starts_at' => normalizeDateTimeLocal((string) $_POST['starts_at']),
            'ends_at' => normalizeDateTimeLocal((string) $_POST['ends_at']),
            'team_id' => !empty($_POST['team_id']) ? (int) $_POST['team_id'] : null,
            'id' => (int) $_POST['event_id'],
        ];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare(
            'UPDATE events SET title = :title, description = :description, category_id = :category_id,
                venue = :venue, address = :address, website = :website,
                starts_at = :starts_at, ends_at = :ends_at, team_id = :team_id
             WHERE ' . $where
        );
        $stmt->execute($params);
        flash('Event updated.');
        redirect('?page=events');
    }

    if ($action === 'delete_event') {
        $params = ['id' => (int) $_POST['event_id']];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND created_by_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare('DELETE FROM events WHERE ' . $where);
        $stmt->execute($params);
        flash('Event deleted.');
        redirect('?page=events');
    }

    if ($action === 'rsvp_event') {
        $stmt = $pdo->prepare(
            'INSERT INTO event_rsvps (event_id, user_id, status)
             VALUES (:event_id, :user_id, :status)
             ON DUPLICATE KEY UPDATE status = VALUES(status), responded_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'event_id' => (int) $_POST['event_id'],
            'user_id' => $user['id'],
            'status' => $_POST['status'] === 'interested' ? 'interested' : 'going',
        ]);
        flash('RSVP saved.');
        redirect('?page=events');
    }
}
