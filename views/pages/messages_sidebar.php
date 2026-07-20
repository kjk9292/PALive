<?php
// Sidebar for the Messages page: compose-a-private-message form.
// Rendered by layout.php into the <aside>; expects $users and $currentUser in scope.
?>
<section class="card">
    <h2>Send a Message</h2>
    <p class="meta">Write privately to another member.</p>
    <form method="post">
        <input type="hidden" name="action" value="send_message">
        <select name="recipient_user_id" required>
            <option value="">Choose recipient</option>
            <?php foreach ($users as $user): ?>
                <?php if ((int) $user['id'] !== (int) $currentUser['id']): ?>
                    <option value="<?= (int) $user['id'] ?>"><?= h($user['name']) ?> (<?= h($user['role']) ?>)</option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <input type="text" name="subject" placeholder="Subject" required>
        <textarea name="body" rows="3" placeholder="Message body" required></textarea>
        <button type="submit">Send Message</button>
    </form>
</section>
