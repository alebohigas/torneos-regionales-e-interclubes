<?php
/**
 * Database Credentials (PRIVATE - DO NOT COMMIT TO GIT)
 * ⚠️  Add this file to .gitignore!
 * Copy credentials.example.php to credentials.php and fill in your values
 */

$DB_HOST = 'localhost';
$DB_USER = 'your_user';
$DB_PASS = 'your_password';
$DB_NAME = 'your_database';
$DB_PORT = 3306;

// ============= SMTP (Pre-Registro email flow) =============
// Used by /api/registro_email.php via PHPMailer (drop sources at
// /api/PHPMailer/{PHPMailer,SMTP,Exception}.php). When PHPMailer is
// missing or these are blank, the code falls back to PHP mail().
//
// El remitente (Username/From) se ROTA dinámicamente desde la tabla
// `cuentas_correo` vía la función MySQL `f_correo()`, que devuelve la
// siguiente cuenta con < 250 envíos del día e incrementa su contador.
// Todos los buzones comparten el mismo $SMTP_PASS (mismo dominio + SPF).
// $SMTP_USER queda como fallback por si f_correo() no regresa cuenta.
$SMTP_HOST      = 'smtp.ionos.mx';
$SMTP_PORT      = 587;
$SMTP_USER      = 'registro.torneo01@speitour.mx';
$SMTP_PASS      = 'your_shared_smtp_password';
$SMTP_FROM_NAME = 'Speitour Registros';
$SMTP_REPLY_TO  = 'noreply@speitour.mx';

// ============= Tabla de usuarios de la app =============
// La app usa `usuarios` (los datos de `usuarios2` se copiaron ahí con la
// migración 2026_08_25_copy_usuarios2_into_usuarios.sql). Solo define esta
// variable si necesitas apuntar a otra tabla.
// $USERS_TABLE = 'usuarios';

