<?php
ob_start();
session_start();

if (!$_SESSION['valid']){
	$_SESSION['state']='guest';
	header("Location:/login.php");
}

?>
<html>
<head>
	<?php include_once('_partial/head.php'); ?>
</head>
<body>

	<?php include_once('_partial/header.php'); ?>

	<div class="main">
		
		<?php include_once('_widgets/download_box.php'); ?>

		<div class="main-inner animate-up">
		
			<div class="lists-pwd">
				<span class="pwd">Searching '<span class="match"><?php echo $_POST['search_file'] ?></span>', total <span class="result-cnt"></span> result(s).</span>

				<button class="home-btn" onclick="javascript:window.location='/'">
					<i class="fas fa-home"></i>
				</button>
				<button class="search-back-btn">
					<i class="fas fa-level-up-alt"></i>
				</button>
				
				<script>
					$('.search-back-btn').click( function(){
						var pwd = '<?php echo $_SESSION['pwd'] ?>';
						if (pwd == '/'){	
							window.location.href = '/';
						}
						else {
							window.location.href = '/render.php?links='+pwd;
						}
						
					});
				</script>
                
				<button class="logout-btn" onclick="javascript:window.location='logout.php'">
					<i class="fas fa-sign-out-alt"></i>
				</button>
			</div>
			<div class="container">
				<table class='file-lists'>
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
						
						searching_file('.',$_POST['search_file']);
						
                        function searching_file($target_dir, $targer_file){
                            if ($handle = scandir($target_dir)) {
                            	foreach ($handle as $file)  {
                                    if (strstr($file, $targer_file)) {
                                        echo "<tr class='animate-up'>";
        
                                        $notmatchstr = str_replace($targer_file, "<span class='match'>$targer_file</span>", $file);

                                        if (!is_file($target_dir.'/'.$file)){
                                            echo "<td class='icon icon-folder'><i class='fas fa-folder'></i></td>";
                                            echo "<td class='name'><a href='/render.php?links=$target_dir/$file'>$notmatchstr</a></td>";
                                            echo "<td class='download'></td>";
                                        }
                                        else {
                                            echo "<td class='icon icon-file'><i class='fas fa-file-alt'></i></td>";
                                            echo "<td class='name'><a href='$target_dir/$file' target='_blank'>$notmatchstr</span></a></td>";
                                            echo "<td class='download' onclick='open_box(`$target_dir/$file`)'><i class='fas fa-cloud-download-alt'></i></td>";
                                        }
                                        
                                        $ftime=date("Y/m/d",filemtime($target_dir.'/'.$file));
                                        echo "<td class='time'><span class='file-meta'>$ftime</span></td></tr>";
                                    }
                                    if (is_dir($file) && $file != "." && $file != ".." ){
                                        searching_file($target_dir.'/'.$file,$targer_file);
                                    }    
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
