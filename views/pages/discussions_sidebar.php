<?php
// Sidebar for the Discussions page: start-a-new-thread form.
// Rendered by layout.php into the <aside>; expects $pdo and $teamOptions in scope.
$discussionCats = fetchAll($pdo, "SELECT id, label FROM lookup_values WHERE type = 'discussion' ORDER BY label = 'Other', label");
?>
<section class="card">
    <h2>Start a Discussion</h2>
    <p class="meta">Open a new thread for the community.</p>
    <form method="post">
        <input type="hidden" name="action" value="create_discussion">
        <input type="text" name="title" placeholder="Thread title" required>
        <select name="category_id" required>
            <option value="">Select category</option>
            <?php foreach ($discussionCats as $dc): ?>
                <option value="<?= (int) $dc['id'] ?>"><?= h((string) $dc['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <textarea name="body" rows="3" placeholder="Start the conversation" required></textarea>
        <select name="team_id">
            <option value="">General community</option>
            <?php foreach ($teamOptions as $teamOption): ?>
                <option value="<?= (int) $teamOption['id'] ?>"><?= h($teamOption['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Post Thread</button>
    </form>
</section>
