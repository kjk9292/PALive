<?php
$teams = fetchAll($pdo, 'SELECT t.*, u.name AS owner_name,
    (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) AS member_count
    FROM teams t
    JOIN users u ON u.id = t.owner_user_id
    ORDER BY t.created_at DESC');
?>
                <section class="card stack">
                    <div class="toolbar">
                        <div class="toolbar-group">
                            <h2 style="margin: 0;">Teams</h2>
                        </div>
                        <?php if ($currentUser): ?>
                            <div class="toolbar-group">
                                <label for="add-team-toggle" class="primary-btn">+ Add team</label>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php foreach ($teams as $team): ?>
                        <?php $canManage = userCanManage($currentUser, $team, 'owner_user_id'); ?>
                        <article class="panel<?= $canManage ? ' has-actions' : '' ?>">
                            <?php if ($canManage): ?>
                                <div class="card-actions">
                                    <label for="edit-team-<?= (int) $team['id'] ?>" class="mini-btn">Edit</label>
                                    <form method="post" class="card-actions-del" onsubmit="return confirm('Delete this team?');">
                                        <input type="hidden" name="action" value="delete_team">
                                        <input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>">
                                        <button type="submit" class="mini-btn mini-danger">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <div class="inline">
                                <strong><?= h($team['name']) ?></strong>
                                <span class="badge"><?= h($team['visibility']) ?></span>
                            </div>
                            <p><?= h($team['description']) ?></p>
                            <div class="meta">Owner: <?= h($team['owner_name']) ?> • <?= (int) $team['member_count'] ?> members</div>
                        </article>
                        <?php if ($canManage): ?>
                            <input type="checkbox" id="edit-team-<?= (int) $team['id'] ?>" class="modal-toggle" hidden>
                            <div class="modal-backdrop">
                                <div class="modal">
                                    <div class="modal-head">
                                        <h2>Edit Team</h2>
                                        <label for="edit-team-<?= (int) $team['id'] ?>" class="modal-x" aria-label="Close">×</label>
                                    </div>
                                    <form method="post" class="stack">
                                        <input type="hidden" name="action" value="update_team">
                                        <input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>">
                                        <label>Name
                                            <input type="text" name="name" value="<?= h($team['name']) ?>" required>
                                        </label>
                                        <label>Description
                                            <textarea name="description" rows="4"><?= h($team['description']) ?></textarea>
                                        </label>
                                        <label>Visibility
                                            <select name="visibility">
                                                <option value="public"<?= $team['visibility'] === 'public' ? ' selected' : '' ?>>public</option>
                                                <option value="private"<?= $team['visibility'] === 'private' ? ' selected' : '' ?>>private</option>
                                            </select>
                                        </label>
                                        <div class="modal-actions">
                                            <label for="edit-team-<?= (int) $team['id'] ?>" class="btn-secondary">Cancel</label>
                                            <button type="submit">Save changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>
<?php if ($currentUser): ?>
    <input type="checkbox" id="add-team-toggle" class="modal-toggle" hidden>
    <div class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h2>Add Team</h2>
                <label for="add-team-toggle" class="modal-x" aria-label="Close">×</label>
            </div>
            <form method="post" class="stack">
                <input type="hidden" name="action" value="create_team">
                <label>Name
                    <input type="text" name="name" required>
                </label>
                <label>Description
                    <textarea name="description" rows="4"></textarea>
                </label>
                <label>Visibility
                    <select name="visibility">
                        <option value="public" selected>public</option>
                        <option value="private">private</option>
                    </select>
                </label>
                <div class="modal-actions">
                    <label for="add-team-toggle" class="btn-secondary">Cancel</label>
                    <button type="submit">Add team</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
