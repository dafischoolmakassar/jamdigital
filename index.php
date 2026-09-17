<?php
/**
 * Kalau folder ini dibuka tanpa nama file spesifik (mis. /app/jamdigital/),
 * lempar otomatis ke view1.php supaya tidak muncul 403 Forbidden.
 */
header('Location: view1.php');
exit;
