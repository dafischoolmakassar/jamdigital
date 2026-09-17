<?php
require __DIR__ . '/_bootstrap.php';
echo json_encode(['loggedIn' => !empty($_SESSION['jasma_admin'])]);
