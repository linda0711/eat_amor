<?php
// ============================================================
//  EAT AMOR — Script de Configuración de Contraseñas
//  Archivo: auth/setup_passwords.php
//
//  ⚠️  EJECUTAR UNA SOLA VEZ desde el navegador:
//      http://localhost/eat_amor/auth/setup_passwords.php
//  ⚠️  ELIMINAR o mover fuera de htdocs después de usarlo
// ============================================================

require_once __DIR__ . '/../config/database.php';

// ── Contraseñas iniciales (cámbialas antes de ejecutar) ─────
$usuarios = [
    ['email' => 'admin@eatamor.com',      'password' => 'Admin123!'],
    ['email' => 'chalco@eatamor.com',     'password' => 'Chalco123!'],
    ['email' => 'texcoco@eatamor.com',    'password' => 'Texcoco123!'],
    ['email' => 'atizapan@eatamor.com',   'password' => 'Atizapan123!'],
    ['email' => 'naucalpan@eatamor.com',  'password' => 'Naucalpan123!'],
    ['email' => 'ixtapaluca@eatamor.com', 'password' => 'Ixtapaluca123!'],
];

$pdo = getDB();
$ok  = [];
$err = [];

foreach ($usuarios as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare(
        'UPDATE usuarios SET password_hash = ? WHERE email = ?'
    );
    if ($stmt->execute([$hash, $u['email']])) {
        $ok[] = "✅ {$u['email']} — contraseña actualizada";
    } else {
        $err[] = "❌ {$u['email']} — error al actualizar";
    }
}

echo '<pre style="font-family:monospace;padding:20px;">';
echo "<h2>Eat Amor — Setup de Contraseñas</h2>\n\n";
foreach ($ok  as $m) echo $m . "\n";
foreach ($err as $m) echo $m . "\n";
echo "\n  Elimina este archivo ahora: auth/setup_passwords.php\n";
echo '</pre>';