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

// ============= SMTP (correos del Pre-Registro) =============
// Lo usa /api/registro_email.php (y todo el flujo de pre-registro) vía
// PHPMailer: deja las fuentes en /api/PHPMailer/{PHPMailer,SMTP,Exception}.php.
// Si falta PHPMailer o el host/contraseña van vacíos, cae a mail() nativo
// (entrega NO garantizada).
//
// Remitente: se ROTA por envío desde la tabla `cuentas_correo`. El código
// detecta el esquema automáticamente:
//   A) id, cuenta_correo, numcorreos, fecha        → contraseña compartida
//   B) idcuentas_correo, cuenta, pwd, acum         → contraseña POR cuenta
// Si la fila trae `pwd`, ésa se usa para autenticar; si no, se usa
// $SMTP_PASS. $SMTP_USER/$SMTP_PASS son el respaldo cuando la tabla no
// existe o todas las cuentas llegaron a su límite diario.
$SMTP_HOST      = 'smtp.ionos.mx';   // servidor del contrato que hospeda los buzones
$SMTP_PORT      = 587;               // 587 STARTTLS · 465 SSL
$SMTP_USER      = 'resguardoacgn@speitour.mx';  // buzón de respaldo
$SMTP_PASS      = 'tu_password_del_buzon';      // contraseña de ese buzón
$SMTP_FROM_NAME = 'Speitour Registros';         // nombre visible del remitente
$SMTP_REPLY_TO  = 'resguardoacgn@speitour.mx';  // a dónde llegan las respuestas

// ============= Tabla de usuarios de la app =============
// La app usa `usuarios` (los datos de `usuarios2` se copiaron ahí con la
// migración 2026_08_25_copy_usuarios2_into_usuarios.sql). Solo define esta
// variable si necesitas apuntar a otra tabla.
// $USERS_TABLE = 'usuarios';

