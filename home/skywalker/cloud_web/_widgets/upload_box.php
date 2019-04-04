<div class="file-upload-box">
	<form class="file-upload" method="post" action="_partial/upload.php" method="post" enctype="multipart/form-data">
		<h4>Select file to upload:</h4>
		<div class="file-location-box">
			<span class="file-location"></span>
		</div>
		<label class="file-upload-btn">
			<input type="file" name="fileToUpload" id="fileToUpload" style="display:none;">
			Pick
		</label>
		<div>
			<button class="ready-upload-btn" type='submit'>Upload</button>
			<button class="close-upload-btn">Cancel</button>
		</div>
	</form>
</div>