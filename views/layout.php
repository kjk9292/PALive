<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>City Focus Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="shell">
    <section class="hero">
        <div class="hero-top">
            <div class="hero-copy">
                <div class="brand">
                    <img class="brand-icon" src="assets/city-icon.svg" alt="Palo Alto" width="48" height="48">
                    <h1>Life in Palo Alto</h1>
                </div>
            </div>
            <section class="header-access">
                <?php if ($currentUser): ?>
                    <p class="meta" style="color: rgba(255,255,255,0.82);">Welcome, <strong><?= h($currentUser['name']) ?></strong>. <label for="edit-user-toggle" class="edit-icon" title="Edit profile" role="button" aria-label="Edit profile">✎</label></p>
                    <form method="post">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit">Sign Out</button>
                    </form>
                <?php else: ?>
                    <details>
                        <summary class="disclosure-summary">
                            <span class="disclosure-label">Member sign in</span>
                            <span class="disclosure-arrow">›</span>
                        </summary>
                        <p class="micro" style="color: rgba(255,255,255,0.78);">For posting and private messages.</p>
                        <form method="post">
                            <input type="hidden" name="action" value="login">
                            <input type="email" name="email" placeholder="Email" required>
                            <input type="password" name="password" placeholder="Password" required>
                            <button type="submit">Sign In</button>
                        </form>
                        <details>
                            <summary class="disclosure-summary">
                                <span class="disclosure-label">Create account</span>
                                <span class="disclosure-arrow">›</span>
                            </summary>
                            <form method="post">
                                <input type="hidden" name="action" value="register">
                                <input type="text" name="name" placeholder="Full name" required>
                                <input type="email" name="email" placeholder="Email" required>
                                <input type="password" name="password" placeholder="Password" required>
                                <input type="text" name="neighborhood" placeholder="Neighborhood">
                                <textarea name="bio" rows="2" placeholder="Short bio"></textarea>
                                <button type="submit">Create Account</button>
                            </form>
                        </details>
                    </details>
                <?php endif; ?>
            </section>
        </div>
        <nav class="nav">
            <a href="?page=dashboard" class="<?= isNavActive('home', $page) ? 'is-active' : '' ?>">Home</a>
            <a href="?page=events" class="<?= isNavActive('events', $page) ? 'is-active' : '' ?>">Events</a>
            <a href="?page=news" class="<?= isNavActive('news', $page) ? 'is-active' : '' ?>">News</a>
            <a href="?page=deals" class="<?= isNavActive('deals', $page) ? 'is-active' : '' ?>">Deals</a>
            <a href="?page=schools" class="<?= isNavActive('schools', $page) ? 'is-active' : '' ?>">Schools</a>
            <details>
                <summary class="<?= isNavActive('community', $page) || isNavActive('resources', $page) ? 'is-active' : '' ?>">Community</summary>
                <div class="nav-menu">
                    <a href="?page=resources">Resources</a>
                    <a href="?page=discussions">Discussions</a>
                    <a href="?page=messages">Messages</a>
                    <a href="?page=teams">Teams</a>
                    <a href="?page=marketplace">Classifieds</a>
                </div>
            </details>
            <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                <a href="?page=admin" class="<?= isNavActive('admin', $page) ? 'is-active' : '' ?>">Admin</a>
            <?php endif; ?>
        </nav>
    </section>

    <?php if ($flash): ?>
        <div class="alert"><?= h($flash) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert"><?= h($error) ?></div>
    <?php endforeach; ?>

    <?php
    // A page gets a sidebar if it ships a matching views/pages/<page>_sidebar.php.
    $sidebarFile = __DIR__ . '/pages/' . $page . '_sidebar.php';
    $hasSidebar = $currentUser && is_file($sidebarFile);
    ?>
    <div class="two-col<?= $hasSidebar ? '' : ' layout-main-only' ?>">
        <main class="stack">
            <?php
            $allowedPages = ['dashboard', 'events', 'news', 'deals', 'resources', 'schools', 'school', 'marketplace', 'discussions', 'messages', 'teams', 'admin'];
            if (in_array($page, $allowedPages, true)) {
                require __DIR__ . '/pages/' . $page . '.php';
            }
            ?>
                    </main>

        <aside class="stack">
            <?php if ($hasSidebar) { require $sidebarFile; } ?>
        </aside>
    </div>
</div>
<?php if ($currentUser): ?>
    <input type="checkbox" id="edit-user-toggle" class="modal-toggle" hidden>
    <div class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h2>Edit Profile</h2>
                <label for="edit-user-toggle" class="modal-x" aria-label="Close">×</label>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_profile">
                <label class="field"><span>Name</span><input type="text" name="name" value="<?= h((string) ($currentUser['name'] ?? '')) ?>" required></label>
                <label class="field"><span>Email</span><input type="email" name="email" value="<?= h((string) ($currentUser['email'] ?? '')) ?>" required></label>
                <label class="field"><span>Neighborhood</span><input type="text" name="neighborhood" value="<?= h((string) ($currentUser['neighborhood'] ?? '')) ?>"></label>
                <label class="field"><span>Bio</span><textarea name="bio" rows="3"><?= h((string) ($currentUser['bio'] ?? '')) ?></textarea></label>
                <label class="field"><span>New password</span><input type="password" name="password" placeholder="Leave blank to keep current" autocomplete="new-password"></label>
                <div class="modal-actions">
                    <label for="edit-user-toggle" class="btn-secondary">Cancel</label>
                    <button type="submit">Save changes</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
