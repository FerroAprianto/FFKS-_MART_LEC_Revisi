<?php
require_once __DIR__ . '/config.php';

$seedUsers = [
    [
        'username' => 'AdminUser',
        'email' => 'admin@ffks.local',
        'age' => 30,
        'password' => 'AdminP@ss123',
        'role' => 'admin',
    ],
    [
        'username' => 'RegularUser',
        'email' => 'user@ffks.local',
        'age' => 25,
        'password' => 'UserP@ss123',
        'role' => 'user',
    ],
];

$log = [];
foreach ($seedUsers as $user) {
    $checkStmt = mysqli_prepare($con, 'SELECT Id FROM users WHERE Email = ?');
    if (!$checkStmt) {
        $log[] = "Gagal menyiapkan periksa email untuk {$user['email']}";
        continue;
    }

    mysqli_stmt_bind_param($checkStmt, 's', $user['email']);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_store_result($checkStmt);

    if (mysqli_stmt_num_rows($checkStmt) > 0) {
        $log[] = "{$user['email']} sudah terdaftar, dilewati.";
        mysqli_stmt_close($checkStmt);
        continue;
    }
    mysqli_stmt_close($checkStmt);

    $insertStmt = mysqli_prepare(
        $con,
        'INSERT INTO users (Username, Email, Age, Password, Role) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$insertStmt) {
        $log[] = "Gagal membuat statement insert untuk {$user['email']}";
        continue;
    }

    $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);
    mysqli_stmt_bind_param(
        $insertStmt,
        'ssiss',
        $user['username'],
        $user['email'],
        $user['age'],
        $passwordHash,
        $user['role']
    );

    if (mysqli_stmt_execute($insertStmt)) {
        $log[] = "Akun {$user['email']} berhasil dibuat ({$user['role']}).";
    } else {
        $log[] = "Gagal memasukkan {$user['email']}: " . mysqli_stmt_error($insertStmt);
    }

    mysqli_stmt_close($insertStmt);
}

$output = implode(PHP_EOL, $log) ?: 'Tidak ada perubahan.';
if (PHP_SAPI === 'cli') {
    echo $output . PHP_EOL;
} else {
    echo '<pre>' . htmlspecialchars($output, ENT_QUOTES, 'UTF-8') . '</pre>';
}
