<?php
require_once '../config/config.php';

$auth = new Auth($conn);
$auth->logout();

FlashMessage::success('Vous avez été déconnecté');
header("Location: login.php");
exit();
?>
