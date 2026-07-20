<?php

declare(strict_types=1);

// Public auth actions (no session required).
function handleAuthActions(PDO $pdo, string $action): void
{
    if ($action === 'register') {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, neighborhood, bio, role)
             VALUES (:name, :email, :password_hash, :neighborhood, :bio, :role)'
        );
        $stmt->execute([
            'name' => trim((string) $_POST['name']),
            'email' => trim((string) $_POST['email']),
            'password_hash' => password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
            'neighborhood' => trim((string) ($_POST['neighborhood'] ?? '')),
            'bio' => trim((string) ($_POST['bio'] ?? '')),
            'role' => 'member',
        ]);
        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
        flash('Account created. You are now signed in.');
        redirect();
    }

    if ($action === 'login') {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => trim((string) $_POST['email'])]);
        $user = $stmt->fetch();

        if (!$user || !password_verify((string) $_POST['password'], $user['password_hash'])) {
            throw new RuntimeException('Invalid email or password.');
        }

        $_SESSION['user_id'] = (int) $user['id'];
        flash('Signed in successfully.');
        redirect();
    }

    if ($action === 'logout') {
        unset($_SESSION['user_id']);
        flash('Signed out.');
        redirect();
    }
}
