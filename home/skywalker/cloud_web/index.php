<?php
ob_start();
session_start();

if (!$_SESSION['valid']){
	$_SESSION['state']='guest';
	echo "<script>window.location.assign('/logout.php');</script>";
}

$_SESSION['pwd']= '/'; 
?>
<html>
	<?php include_once('_partial/head.php'); ?>
<body >
	
	<?php include_once('_partial/info_block.php'); ?>
	<?php include_once('_partial/header.php'); ?>

	<div class="main">

		<?php include_once('_widgets/download_box.php'); ?>
		<?php include_once('_widgets/delete_box.php'); ?>
		<?php include_once('_widgets/upload_box.php'); ?>

		<div class="main-inner">
		
			<div class="lists-pwd animate-up">
				<button class="upload-btn">
					<i class="fas fa-cloud-upload-alt"></i>
				</button>
				<span class="pwd">~</span>

				<button class="logout-btn" onclick="javascript:window.location='logout.php'">
					<i class="fas fa-sign-out-alt"></i>
				</button>
			</div>

			<div class="container">
				<table class='animate-up file-lists'>
					<thead>
						<tr class='animate-up'>
							<th class='type'>
								<span class="debug debug-type">Type</span>
							</th>
							<th class='name' >
								<span class="debug" onclick='sort_table(1)'>Name</span>
								<i class="fas fa-sort-up"></i>
								<i class="fas fa-sort-down"></i>
							</th>
							<th class='download'>
								<span class="debug debug-download"></span>
							</th>
							<th class='th-time'>
								<span class="debug" onclick='sort_table(3)'>Time</span>
								<i class="fas fa-sort-up"></i>
								<i class="fas fa-sort-down"></i>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ($handle = scandir('.')) {
							foreach ($handle as $file)  {
								if ($file != "." && $file != ".." && $file[0]!="." && !preg_match("/[a-zA-Z0-9]?\.php/", $file) && $file[0] != "_"){
									echo "<tr class='animate-up'>";

									if (!is_file($file)){
										echo "<td class='icon icon-folder'><i class='fas fa-folder'></i></td>";
										echo "<td class='name'><a href='/render.php?links=$file'>$file</a></td>";
										echo "<td class='download'></td>";
									}
									else {
										echo "<td class='icon icon-file'><i class='fas fa-file-alt'></i></td>";
										echo "<td class='name'><a href='$file' target='_blank'>$file</a></td>";
										echo "<td class='download' onclick='open_box(`$file`)'><i class='fas fa-cloud-download-alt'></i></td>";
									}

									$ftime=date("Y/m/d",filemtime($file));
									echo "<td class='time'><span class='file-meta'>$ftime</span></td></tr>";
								}	
							}
						}
						?>
					</tbody>
				</table>
			</div>
		</div>
		
		<?php include_once('_partial/footer.php'); ?>				
	</div>
</body>
</html>
