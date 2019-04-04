<?php

ob_start();
session_start();


$target_dir = "/home/skywalker/cloud_web/".$_SESSION['pwd'].'/';
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;
echo $_FILES["fileToUpload"]["tmp_name"];

if (file_exists($target_file)) {
    $_SESSION['upload_result'] = 'exists';
    $uploadOk = 0;
}

if ($_FILES["fileToUpload"]["name"]==""){
    $_SESSION['upload_result'] = 'failed';
    $uploadOk = 0;
    echo 'no';
}

if ($uploadOk) {
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        $_SESSION['upload_result'] = 'success';
    } else {
        $_SESSION['upload_result'] = 'failed';
        echo 'failed';
    }
}

if ($_SESSION['pwd']=='/')
    header("Location:/");
else
    header("Location:/render.php?links=".$_SESSION['pwd']);

?>