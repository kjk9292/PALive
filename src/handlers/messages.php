<?php

declare(strict_types=1);

// Direct messages.
function handleMessageActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'send_message') {
        $recipientId = (int) $_POST['recipient_user_id'];
        $stmt = $pdo->prepare(
            'INSERT INTO messages (sender_user_id, recipient_user_id, subject, body)
             VALUES (:sender_user_id, :recipient_user_id, :subject, :body)'
        );
        $stmt->execute([
            'sender_user_id' => $user['id'],
            'recipient_user_id' => $recipientId,
            'subject' => trim((string) $_POST['subject']),
            'body' => trim((string) $_POST['body']),
        ]);
        createNotification($pdo, $recipientId, 'message', 'You received a new private message.', '?page=messages');
        flash('Message sent.');
        redirect('?page=messages');
    }

    if ($action === 'delete_message') {
        $params = ['id' => (int) $_POST['message_id']];
        $where = 'id = :id';
        if (!isAdminUser($user)) {
            $where .= ' AND sender_user_id = :uid';
            $params['uid'] = $user['id'];
        }
        $stmt = $pdo->prepare('DELETE FROM messages WHERE ' . $where);
        $stmt->execute($params);
        flash('Message deleted.');
        redirect('?page=messages');
    }
}
