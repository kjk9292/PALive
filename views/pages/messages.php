<?php
$messages = [];
if ($currentUser) {
    $messages = fetchAll($pdo, 'SELECT m.*, s.name AS sender_name, r.name AS recipient_name
        FROM messages m
        JOIN users s ON s.id = m.sender_user_id
        JOIN users r ON r.id = m.recipient_user_id
        WHERE m.sender_user_id = :sender_id OR m.recipient_user_id = :recipient_id
        ORDER BY m.created_at DESC', [
            'sender_id' => $currentUser['id'],
            'recipient_id' => $currentUser['id'],
        ]);
}
?>
                <section class="card stack">
                    <h2>Private Messages</h2>
                    <?php if (!$currentUser): ?>
                        <p>Sign in to send and receive member messages.</p>
                    <?php elseif ($messages === []): ?>
                        <p class="meta">No messages yet. Use “Send a Message” on the right to start a conversation.</p>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <?php $canManage = userCanManage($currentUser, $message, 'sender_user_id'); ?>
                            <article class="panel<?= $canManage ? ' has-actions' : '' ?>">
                                <?php if ($canManage): ?>
                                    <div class="card-actions">
                                        <form method="post" class="card-actions-del" onsubmit="return confirm('Delete this message?');">
                                            <input type="hidden" name="action" value="delete_message">
                                            <input type="hidden" name="message_id" value="<?= (int) $message['id'] ?>">
                                            <button type="submit" class="mini-btn mini-danger">Delete</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                <strong><?= h($message['subject']) ?></strong>
                                <p><?= h($message['body']) ?></p>
                                <div class="meta">From <?= h($message['sender_name']) ?> to <?= h($message['recipient_name']) ?> • <?= h($message['created_at']) ?></div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
