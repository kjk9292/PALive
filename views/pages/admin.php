<?php
$isAdmin = isAdminUser($currentUser);
$adminTab = $_GET['tab'] ?? 'dashboard';
if (!in_array($adminTab, ['dashboard', 'users', 'categories'], true)) {
    $adminTab = 'dashboard';
}
if (!$isAdmin) {
    $adminTab = 'dashboard';
}
$lookupGroups = [];
if ($isAdmin) {
    foreach (['event' => 'Event Types', 'news' => 'News Categories', 'deal' => 'Deal Categories', 'resource' => 'Resource Categories', 'discussion' => 'Discussion Categories'] as $lkType => $lkTitle) {
        $lookupGroups[$lkType] = [
            'title' => $lkTitle,
            'items' => fetchAll($pdo, "SELECT id, label FROM lookup_values WHERE type = :t ORDER BY label = 'Other', label", ['t' => $lkType]),
        ];
    }
}
?>
                <section class="card stack">
                    <div class="toolbar">
                        <div class="toolbar-group">
                            <h2 style="margin: 0;">Admin</h2>
                            <a href="?page=admin&tab=dashboard" class="<?= $adminTab === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
                            <?php if ($isAdmin): ?>
                                <a href="?page=admin&tab=users" class="<?= $adminTab === 'users' ? 'is-active' : '' ?>">Users</a>
                                <a href="?page=admin&tab=categories" class="<?= $adminTab === 'categories' ? 'is-active' : '' ?>">Categories</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <?php if ($adminTab === 'dashboard'): ?>
                <section class="card">
                    <h2>Dashboard</h2>
                    <p class="meta">Site activity at a glance.</p>
                    <div class="cards">
                        <div class="panel"><strong><?= $stats['members'] ?></strong><br><span class="meta">Registered users</span></div>
                        <div class="panel"><strong><?= $stats['visits'] ?></strong><br><span class="meta">Visitors</span></div>
                        <div class="panel"><strong><?= $stats['unique_visitors'] ?></strong><br><span class="meta">Unique visitors</span></div>
                        <div class="panel"><strong><?= $stats['events'] ?></strong><br><span class="meta">Published events</span></div>
                        <div class="panel"><strong><?= $stats['discussions'] ?></strong><br><span class="meta">Active discussions</span></div>
                        <div class="panel"><strong><?= $stats['schools'] ?></strong><br><span class="meta">Schools</span></div>
                        <div class="panel"><strong><?= $stats['marketplace'] ?></strong><br><span class="meta">Marketplace listings</span></div>
                        <div class="panel"><strong><?= $stats['notifications'] ?></strong><br><span class="meta">Your unread alerts</span></div>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($isAdmin && $adminTab === 'users'): ?>
                <section class="card stack">
                    <div class="toolbar">
                        <div class="toolbar-group">
                            <h2 style="margin: 0;">Users</h2>
                        </div>
                        <div class="toolbar-group">
                            <label for="add-user" class="primary-btn">+ Add user</label>
                        </div>
                    </div>
                    <?php foreach ($users as $row): ?>
                        <article class="panel has-actions">
                            <div>
                                <strong><?= h((string) $row['name']) ?></strong>
                                <span class="meta">#<?= (int) $row['id'] ?> · <?= h((string) $row['email']) ?> · <?= h((string) $row['role']) ?></span>
                            </div>
                            <div class="card-actions">
                                <label for="edit-user-<?= (int) $row['id'] ?>" class="mini-btn">Edit</label>
                                <?php if ((int) $row['id'] !== (int) $currentUser['id']): ?>
                                    <form method="post" class="card-actions-del" onsubmit="return confirm('Delete this user?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="mini-btn mini-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                        <input type="checkbox" id="edit-user-<?= (int) $row['id'] ?>" class="modal-toggle" hidden>
                        <div class="modal-backdrop">
                            <div class="modal">
                                <div class="modal-head">
                                    <h2>Edit User</h2>
                                    <label for="edit-user-<?= (int) $row['id'] ?>" class="modal-x" aria-label="Close">×</label>
                                </div>
                                <form method="post" class="stack">
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <label class="field"><span>Name</span><input type="text" name="name" value="<?= h((string) $row['name']) ?>" required></label>
                                    <label class="field"><span>Email</span><input type="email" name="email" value="<?= h((string) $row['email']) ?>" required></label>
                                    <label class="field"><span>Role</span>
                                        <select name="role">
                                            <option value="member"<?= $row['role'] === 'member' ? ' selected' : '' ?>>Member</option>
                                            <option value="moderator"<?= $row['role'] === 'moderator' ? ' selected' : '' ?>>Moderator</option>
                                            <option value="admin"<?= $row['role'] === 'admin' ? ' selected' : '' ?>>Admin</option>
                                        </select>
                                    </label>
                                    <label class="field"><span>New password</span><input type="password" name="password" placeholder="Leave blank to keep current" autocomplete="new-password"></label>
                                    <div class="modal-actions">
                                        <label for="edit-user-<?= (int) $row['id'] ?>" class="btn-secondary">Cancel</label>
                                        <button type="submit">Save changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>

                <input type="checkbox" id="add-user" class="modal-toggle" hidden>
                <div class="modal-backdrop">
                    <div class="modal">
                        <div class="modal-head">
                            <h2>Add User</h2>
                            <label for="add-user" class="modal-x" aria-label="Close">×</label>
                        </div>
                        <form method="post" class="stack">
                            <input type="hidden" name="action" value="create_user">
                            <label class="field"><span>Name</span><input type="text" name="name" required></label>
                            <label class="field"><span>Email</span><input type="email" name="email" required></label>
                            <label class="field"><span>Password</span><input type="password" name="password" required autocomplete="new-password"></label>
                            <label class="field"><span>Role</span>
                                <select name="role">
                                    <option value="member" selected>Member</option>
                                    <option value="moderator">Moderator</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </label>
                            <div class="modal-actions">
                                <label for="add-user" class="btn-secondary">Cancel</label>
                                <button type="submit">Add user</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($isAdmin && $adminTab === 'categories'): ?>
                <section class="card stack">
                    <h2>Manage Categories</h2>
                    <p class="meta">Categories and types used across the site. "Other" stays last in each list.</p>
                    <div class="cards">
                        <?php foreach ($lookupGroups as $lkType => $lkInfo): ?>
                            <div class="panel stack lookup-card">
                                <h3 style="margin: 0;"><?= h($lkInfo['title']) ?> <span class="meta">(<?= count($lkInfo['items']) ?>)</span></h3>
                                <div class="lookup-items stack">
                                    <?php foreach ($lkInfo['items'] as $lk): ?>
                                        <div class="lookup-row">
                                            <form method="post" class="lookup-edit">
                                                <input type="hidden" name="action" value="update_lookup">
                                                <input type="hidden" name="lookup_id" value="<?= (int) $lk['id'] ?>">
                                                <input type="text" name="label" value="<?= h((string) $lk['label']) ?>" required>
                                                <button type="submit" class="mini-btn">Save</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Delete this category? Items using it will become uncategorized.');">
                                                <input type="hidden" name="action" value="delete_lookup">
                                                <input type="hidden" name="lookup_id" value="<?= (int) $lk['id'] ?>">
                                                <button type="submit" class="mini-btn mini-danger">Delete</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <form method="post" class="lookup-add">
                                    <input type="hidden" name="action" value="create_lookup">
                                    <input type="hidden" name="lookup_type" value="<?= h($lkType) ?>">
                                    <input type="text" name="label" placeholder="New category" required>
                                    <button type="submit" class="primary-btn">Add</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
