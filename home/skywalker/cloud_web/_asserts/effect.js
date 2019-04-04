$(document).ready(function() {
	var now_page = window.location.pathname;
	var should_sort = ['/', '/render.php', '/do_search.php'];

	if (should_sort.indexOf(now_page) != -1)
		sort_table(1);

	if (now_page == '/login.php')
		$('.main').animate({opacity: 1, bottom: 0},700);

	if (now_page == '/do_search.php'){
		var cnt = $('table tr').length-1;
		$('.result-cnt').text(cnt);
	}

	$.when(
		$('h1').animate({opacity: 1, top: 0},700),
		$('.lists-pwd').animate({opacity: 1, bottom: 0},700),
		$('table').animate({opacity: 1, bottom: 0},700)
	)
	.then(
		function(){
			return $('.success-info, .failed-info, .warning-info').animate({opacity: 1, top: 0},700)
		}
	)
	
	var table_element = $('table tr');
	var each_element  = $.makeArray(table_element).map( function(currentValue, index) {
		return $(currentValue).delay(index*20+100).animate({ opacity: 1 }).promise();
	})

	setTimeout( function() {
			$('.info-block').animate({opacity: 0, top: -50},700);
	}, 3000);

	$('td.name a').each( function() {
		if($(this).text().length > 25){
			$(this).attr("title",$(this).text());
            var text=$(this).text().substring(0,24)+"...";
            $(this).text(text);
		}
	});

	$('.login').click( function() {
		var flag = true;

		if(!$('.account-box input').val() || !$('.password-box input').val()){
			$('input').attr('required',true);
			$('.login').attrA
			flag = false;
		}

		return flag;
	});

	$('.upload-btn, .close-upload-btn').click( function(){
		$('.file-upload-box').toggleClass("file-upload-box-on");
		$('.main-inner').toggleClass('disable');

		if ($(this).hasClass('upload-btn'))
			check_input();

			return false;
	});	

	$('.back-btn').click( function(){
		var nowurl = location.search;
		var pwd = nowurl.split('?')[1].split('=')[1];
		var pwd_list = pwd.split('/');
		if (pwd_list.length == 1){	
			window.location.href = '/';
		}
		else {
			nowurl = nowurl.substring(0,nowurl.length - pwd_list[pwd_list.length-1].length -1 );
			window.location.href = '/render.php'+nowurl;
		}
		
	});
	
	var $which_delete;
	
	$('.icon-file').click( function(){
		$('.delete-check-box').addClass('delete-check-box-on');
		$('.main-inner, header, footer').addClass('disable');
		$('.delete-check-box h4').text('Delete "' + $(this).parent().children('.name').text() + '" ?');

		$which_delete = $(this).parent();
		console.log($which_delete);
	});

	$('.delete-check-box .check-btn:last-child').click( function(){
		$('.main-inner, header, footer').removeClass('disable');
		$('.delete-check-box').removeClass('delete-check-box-on');
	});

	$('.delete-check-box .check-btn:first-child').click( function(){

		var should_delete = $which_delete.children().children(1)[1].href
		var del_arr 	  = should_delete.split('/');
		var should_delete = del_arr[del_arr.length-1];

		$.ajax({
			url: '_partial/do_delete.php',
			type: 'post',
			data: { 'file': should_delete},
			error: function (xhr) { },
			success: function (response) {
				//alert(response);
			}
		});

		location.reload();
	});
});

function run_dot(){
	var dots=document.getElementById('dot');
	if(dots.innerHTML.length>3)
		dots.innerHTML="";
	else
		dots.innerHTML+=".";
}

function sort_table(n){
	var tables, rows, swap, is_asc, i, x, y, does, cnt; 
	
	tables  = document.getElementsByTagName("table")[0];
	swap   = true;
	is_asc = true;
	cnt=0;

	var th = document.getElementsByTagName("TH")[n];

	$('th').removeClass('asc');
	$('th').removeClass('dec');

	while (swap) {
		swap = false;
		rows = tables.rows;
	
		for (i=1;i<(rows.length-1);i++) {
			does = false;

			x=rows[i].getElementsByTagName("TD")[n];
			y=rows[i+1].getElementsByTagName("TD")[n];
			
			if (is_asc){
				if (x.innerHTML.toLowerCase()>y.innerHTML.toLowerCase()){
					does=true;
					break;
				}
			}
			else {
				if (x.innerHTML.toLowerCase()<y.innerHTML.toLowerCase()){
					does=true;
					break;
				}
			}

		}

		if (does) {
			rows[i].parentNode.insertBefore(rows[i+1], rows[i]);
			swap=true;
			cnt++;
		}
		else {
			if (!cnt && is_asc){
				is_asc=false;
				swap=true;
			}
		}

	}
	
	if (is_asc){
		th.classList.add('asc');
	}
	else {
		th.classList.add('dec');
	}

}

function close_box() {
	$('.download-check-box').removeClass('download-check-box-on');
	$('.main-inner').removeClass('disable');
}

function open_box(file){
	$('.download-check-box').addClass('download-check-box-on');
	$('.main-inner').addClass('disable');

	var file_s = file.split('/')
	file_s = file_s[file_s.length - 1];

	$('.download-check-box h4').text('Download "' + file_s + '" ?')

	$('.check-btn > a').attr({
		"href": file,
		"download" : file
	})
}

function check_input(){
	const fileUploader = document.querySelector('#fileToUpload');

	fileUploader.addEventListener('change', (e) => {
		$('.file-location').text(e.target.files[0].name);
	});

	return true;
}
  