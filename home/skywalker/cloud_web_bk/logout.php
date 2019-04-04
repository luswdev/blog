<?php
ob_start();
session_start();

if (!$_SESSION['valid']){
	$_SESSION['state']='guest';
	header("Location:/login.php");
}
else{
	unset($_SESSION['account']);
	unset($_SESSION['password']);
	unset($_SESSION['valid']);

	$_SESSION['state']='logout';

	$mysqli = new mysqli('localhost', 'callumlu', 'callum1996', 'cloud_db');
	$sql = "UPDATE `login_log` SET `logout_time` = ? WHERE `login_id` = ? ";
	$stmt = $mysqli->prepare($sql);
	$times=date("Y-n-d H:i:s");
	$stmt->bind_param('si', $times ,$_SESSION['login_id']);
	$stmt->execute();
	$stmt->close();

	unset($_SESSION['login_id']);
	unset($_SESSION['pwd']);

}

#header('Refresh: 2; URL=/login.php')

?>
<html>
<head>
	<?php include_once('_partial/head.php'); ?>
</head>
<body>
	<div class="logout-text">
		Login out
		<span id="dot">.</span>
	</div>
</body>
</html>
