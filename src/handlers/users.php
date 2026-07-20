<?php

declare(strict_types=1);

// Profile + admin user management.
function handleUserActions(PDO $pdo, array $user, string $action): void
{
    if ($action === 'update_profile') {
        $name = trim((string) $_POST['name']);
        $email = trim((string) $_POST['email']);
        if ($name === '' || $email === '') {
            throw new RuntimeException('Name and email are required.');
        }

        $fields = 'name = :name, email = :email, neighborhood = :neighborhood, bio = :bio';
        $params = [
            'name' => $name,
            'email' => $email,
            'neighborhood' => trim((string) ($_POST['neighborhood'] ?? '')),
            'bio' => trim((string) ($_POST['bio'] ?? '')),
            'id' => $user['id'],
        ];

        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '') {
            $fields .= ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = $pdo->prepare("UPDATE users SET $fields WHERE id = :id");
        $stmt->execute($params);
        flash('Profile updated.');
        redirect();
    }

    if ($action === 'create_user') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $name = trim((string) $_POST['name']);
        $email = trim((string) $_POST['email']);
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || $email === '' || $password === '') {
            throw new RuntimeException('Name, email, and password are required.');
        }
        $role = in_array($_POST['role'] ?? '', ['member', 'moderator', 'admin'], true) ? $_POST['role'] : 'member';
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role)
             VALUES (:name, :email, :password_hash, :role)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);
        flash('User created.');
        redirect('?page=admin');
    }

    if ($action === 'update_user') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $name = trim((string) $_POST['name']);
        $email = trim((string) $_POST['email']);
        if ($name === '' || $email === '') {
            throw new RuntimeException('Name and email are required.');
        }
        $role = in_array($_POST['role'] ?? '', ['member', 'moderator', 'admin'], true) ? $_POST['role'] : 'member';
        $fields = 'name = :name, email = :email, role = :role';
        $params = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'id' => (int) $_POST['user_id'],
        ];
        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '') {
            $fields .= ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $stmt = $pdo->prepare("UPDATE users SET $fields WHERE id = :id");
        $stmt->execute($params);
        flash('User updated.');
        redirect('?page=admin');
    }

    if ($action === 'delete_user') {
        if (!isAdminUser($user)) {
            throw new RuntimeException('Not authorized.');
        }
        $targetId = (int) $_POST['user_id'];
        if ($targetId === (int) $user['id']) {
            throw new RuntimeException('You cannot delete your own account.');
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $targetId]);
        flash('User deleted.');
        redirect('?page=admin');
    }
}
