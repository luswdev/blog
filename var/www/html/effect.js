$(document).ready(function() {
	var d = new Date();
	var now_m = d.getMonth() +1;
	var now_d = d.getDate();
	$('.clock-M').text(now_m);
	$('.clock-d').text(now_d);
	refrash_clock();
	setInterval(refrash_clock,1000);

	$.when(
		$('h1').animate({opacity: 1, top: 50}, 1000)
	)
	.then( function () {
		return $.when(
		$('.links a:first-child').animate({opacity: 1, left: 0},400),
		$('.links a:last-child').animate({opacity: 1, right: 0},400)
		)
	})
	.then( function () {
		return $('.links hr').animate({opacity: 1, right: 0, top: 0},400);
	})
	.then(function () {
		return $('.site-clock').animate({opacity: 1 },400);
	})
	
});

function refrash_clock(){
	var d = new Date();
	var now_h = d.getHours();
	var now_m = d.getMinutes();

	if ($('.clock-h').text()=="")
		$('.clock-h').text(now_h);

	if ($('.clock-m').text()=="")
		$('.clock-m').text(now_m);

	if ($('.clock-h').text()!=now_h )
		animate_digtal('.clock-h',now_h);

	
	if (now_m<10)
		now_m= '0' + now_m;

	if ($('.clock-m').text()!=now_m)
		animate_digtal('.clock-m',now_m);

	//$('.clock-m').text(now_m);

	$.when(
		$('.split').animate({opacity: 0},100).delay(400)
	)
	.then( function(){
		return $('.split').animate({opacity: 1},100)
	})
}

function animate_digtal(element, values) {
	$.when(
		$(element).animate({ opacity: 0, top: 10 },400)
	)
	.then( function(){
		$(element).animate({ top: -10}, 10);
		$(element).text(values);
		return $(element).animate({  opacity: 1, top: 0 },400)
	})
}
