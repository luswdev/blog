<?php
ob_start();
session_start();

$file =  $_POST['file'];
$file = urldecode($file);

if ($_SESSION['pwd']!='/')
    $file =  $_SESSION['pwd'].'/'.$file;

$file='../'.$file;

if (file_exists($file)) {
    unlink($file);
    $_SESSION['delete_result'] = 'success';
}
else
    $_SESSION['delete_result'] = 'failed';

?>