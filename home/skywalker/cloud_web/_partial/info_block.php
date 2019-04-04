<div class='account-info'>

<?php

if (isset($_SESSION['state'])) {
	if ($_SESSION['state'] == 'success'){
	
		include_once('_partial/db.php');
		$mysqli = new mysqli('localhost', 'callumlu', 'callum1996', 'cloud_db');
		$sql = "SELECT `login_time` FROM `login_log` WHERE `account` = ? ORDER BY `login_id` desc";
		$stmt = $mysqli->prepare($sql);
		$stmt->bind_param('s',$_SESSION['account']);
		$stmt->execute();
		$stmt->bind_result($times);
		$stmt->fetch();
	
		echo "
		<div class='success-info info-block animate-down'>
		Welcome back! ".$_SESSION['account']."<br>".
		"Last visited at ".$times.
		"</div>";
  }
  else if ($_SESSION['state'] == 'bad'){
		echo "
		<div class='failed-info info-block animate-down'>
		Incorrect username or password.	
		</div>";
  }
	else if ($_SESSION['state'] == 'logout'){
		echo "
		<div class='success-info info-block animate-down'>
		Successful logout.	
		</div>";
	}
	else if ($_SESSION['state'] == 'guest'){
		echo "
		<div class='warning-info info-block animate-down'>
		Please login first.	
		</div>";
	}

	unset($_SESSION['state']);
}

if (isset($_SESSION['upload_result'])) {
	if ($_SESSION['upload_result'] == 'exists'){
		echo "
		<div class='account-info'>
		<div class='warning-info info-block animate-down'>
			Sorry, the file is exist. 
		</div>";
	}
	else if ($_SESSION['upload_result'] == 'success'){
		echo "
		<div class='account-info'>
		<div class='success-info info-block animate-down'>
			The file is successfully upload.
		</div>";
	}
	else if ($_SESSION['upload_result'] == 'failed'){
		echo "
		<div class='account-info'>
		<div class='failed-info info-block animate-down'>
			There was a error occur when the file was uploading.
		</div>";
	}

	unset($_SESSION['upload_result']);
}

if (isset($_SESSION['delete_result'])) {
	if ($_SESSION['delete_result'] == 'failed'){
		echo "
		<div class='account-info'>
		<div class='failed-info info-block animate-down'>
		There was a error occur when the file was deleting.
		</div>";
	}
	else if ($_SESSION['delete_result'] == 'success'){
		echo "
		<div class='account-info'>
		<div class='success-info info-block animate-down'>
		The file is successfully delete.
		</div>";
	}
		
	unset($_SESSION['delete_result']);
}

?>

</div>
