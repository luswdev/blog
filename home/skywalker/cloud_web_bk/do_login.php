<?php
ob_start();
session_start();
?>

<html>
	<?php include_once('_partial/head.php'); ?>
<body>
	<div class="logout-text">
		Sign in
		<span id="dot">.</span>
	</div>

	<?php
	define('BOT_TOKEN', '868385679:AAHea69gcXkC19t85sCx7BUbgFhWWCqpdQc');
	define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
	$chatID = 460873343;

	$mysqli = new mysqli('localhost', YOURID, YOURPASSWD, YOURDB);

	$sql = "SELECT DECODE(passwd, 'dcdclab') FROM users_list WHERE account = ?";
	$stmt = $mysqli->prepare($sql);
	$stmt->bind_param('s',$_POST['account']);
	$stmt->execute();
	$stmt->bind_result($passwd);
	$stmt->fetch();
	$stmt->close();

	if (!empty($_SERVER["HTTP_CLIENT_IP"])){
		$ip = $_SERVER["HTTP_CLIENT_IP"];
	}else if(!empty($_SERVER["HTTP_X_FORWARDED_FOR"])){
		$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}else{
		$ip = $_SERVER["REMOTE_ADDR"];
	}

	$_SESSION['ip'] = $ip;
	$_SESSION['account'] = $_POST['account'];

	if ( $passwd == $_POST['password'] && $_POST['password']!='' ){

		$_SESSION['valid'] = true;
		$_SESSION['timeout'] = time();
		$_SESSION['state'] = 'success';
		

		$insert_sql = "INSERT INTO `login_log` ( account, login_time, login_ip) VALUES (?,?,?)";
		$stmt = $mysqli->prepare($insert_sql);
		$stmt->bind_param('sss', $_POST['account'], date("Y-n-d H:i:s"), $ip);
		$stmt->execute();

		$find_id = "SELECT LAST_INSERT_ID()";
		$stmt = $mysqli->prepare($find_id);
		$stmt->execute();
		$stmt->bind_result($id);
		$stmt->fetch();
		$stmt->close();

		$_SESSION['login_id'] = $id;
		
		$reply  = "💀`Sign in WARNING`💀".'%0A'.'%0A'.
				"Someone sign in successfully, is you?🤔🤔".'%0A'.'%0A'."---".'%0A'.
				"🖥️ *IP:* `".$ip."`".'%0A'.
				"💩 *User:* _".$_POST['account']."_".'%0A'.
				"👀 [See more detail](https://file.omuskywalker.com/phpmyadmin)";

		$sendto = API_URL."sendmessage?chat_id=".$chatID."&text=".$reply."&parse_mode=markdown";
		file_get_contents($sendto);

		header("Location:/");
	}
	else {
		$_SESSION['valid'] = false;
		$_SESSION['state'] = 'bad';

		$insert_sql = "INSERT INTO `login_error_log` ( trying_account, trying_time, trying_ip) VALUES (?,?,?)";
		$stmt = $mysqli->prepare($insert_sql);
		$stmt->bind_param('sss', $_POST['account'], date("Y-n-d H:i:s"), $ip);
		$stmt->execute();
		$stmt->close();
		
		$reply  = "😈`Attack WARNING`😈".'%0A'.'%0A'.
				"Someone trying to sign in, but failed.".'%0A'.
				"HA!HA!🤣🤣".'%0A'.'%0A'."---".'%0A'.
				"🖥️ *IP:* `".$ip."`".'%0A'.
				"💩 *User:* _".$_POST['account']."_".'%0A'.
				"👀 [See more detail](https://file.omuskywalker.com/phpmyadmin)";

		$sendto = API_URL."sendmessage?chat_id=".$chatID."&text=".$reply."&parse_mode=markdown";
		file_get_contents($sendto);

		header("Location:/login.php");
	}
	?>
</body>
</html>
