<?php
$discussions = fetchAll($pdo, 'SELECT d.*, u.name AS creator_name, t.name AS team_name, l.label AS category,
    (SELECT COUNT(*) FROM discussion_replies dr WHERE dr.discussion_id = d.id) AS reply_count
    FROM discussions d
    JOIN users u ON u.id = d.created_by_user_id
    LEFT JOIN teams t ON t.id = d.team_id
    LEFT JOIN lookup_values l ON l.id = d.category_id
    ORDER BY d.is_pinned DESC, d.created_at DESC');
$replies = fetchAll($pdo, 'SELECT dr.*, u.name FROM discussion_replies dr JOIN users u ON u.id = dr.user_id ORDER BY dr.created_at ASC');
$discussionCategoryOptions = fetchAll($pdo, "SELECT id, label FROM lookup_values WHERE type = 'discussion' ORDER BY label = 'Other', label");
?>
                <section class="card stack">
                    <h2>Bulletin Board</h2>
                    <?php foreach ($discussions as $discussion): ?>
                        <?php $canManage = userCanManage($currentUser, $discussion, 'created_by_user_id'); ?>
                        <article class="panel<?= $canManage ? ' has-actions' : '' ?>">
                            <?php if ($canManage): ?>
                                <div class="card-actions">
                                    <label for="edit-discussion-<?= (int) $discussion['id'] ?>" class="mini-btn">Edit</label>
                                    <form method="post" class="card-actions-del" onsubmit="return confirm('Delete this discussion?');">
                                        <input type="hidden" name="action" value="delete_discussion">
                                        <input type="hidden" name="discussion_id" value="<?= (int) $discussion['id'] ?>">
                                        <button type="submit" class="mini-btn mini-danger">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <div class="inline">
                                <strong><?= h($discussion['title']) ?></strong>
                                <?php if ((int) $discussion['is_pinned'] === 1): ?><span class="badge">Pinned</span><?php endif; ?>
                            </div>
                            <p><?= h($discussion['body']) ?></p>
                            <div class="meta"><?= h($discussion['category']) ?> • <?= h($discussion['creator_name']) ?> • <?= (int) $discussion['reply_count'] ?> replies</div>
                            <?php foreach ($replies as $reply): ?>
                                <?php if ((int) $reply['discussion_id'] === (int) $discussion['id']): ?>
                                    <div class="reply meta"><?= h($reply['name']) ?>: <?= h($reply['body']) ?></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($currentUser): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="reply_discussion">
                                    <input type="hidden" name="discussion_id" value="<?= (int) $discussion['id'] ?>">
                                    <textarea name="body" rows="2" placeholder="Reply to this thread" required></textarea>
                                    <button type="submit">Post Reply</button>
                                </form>
                            <?php endif; ?>
                        </article>
                        <?php if ($canManage): ?>
                            <input type="checkbox" id="edit-discussion-<?= (int) $discussion['id'] ?>" class="modal-toggle" hidden>
                            <div class="modal-backdrop">
                                <div class="modal">
                                    <div class="modal-head">
                                        <h2>Edit Discussion</h2>
                                        <label for="edit-discussion-<?= (int) $discussion['id'] ?>" class="modal-x" aria-label="Close">×</label>
                                    </div>
                                    <form method="post" class="stack">
                                        <input type="hidden" name="action" value="update_discussion">
                                        <input type="hidden" name="discussion_id" value="<?= (int) $discussion['id'] ?>">
                                        <input type="text" name="title" placeholder="Thread title" value="<?= h($discussion['title']) ?>" required>
                                        <select name="category_id" required>
                                            <option value="">Select category</option>
                                            <?php foreach ($discussionCategoryOptions as $dc): ?>
                                                <option value="<?= (int) $dc['id'] ?>"<?= (int) ($discussion['category_id'] ?? 0) === (int) $dc['id'] ? ' selected' : '' ?>><?= h((string) $dc['label']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <textarea name="body" rows="3" placeholder="Start the conversation" required><?= h($discussion['body']) ?></textarea>
                                        <select name="team_id">
                                            <option value="">General community</option>
                                            <?php foreach ($teamOptions as $teamOption): ?>
                                                <option value="<?= (int) $teamOption['id'] ?>"<?= (int) ($discussion['team_id'] ?? 0) === (int) $teamOption['id'] ? ' selected' : '' ?>><?= h($teamOption['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="modal-actions">
                                            <label for="edit-discussion-<?= (int) $discussion['id'] ?>" class="btn-secondary">Cancel</label>
                                            <button type="submit">Save changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>
