<?php
	$siteurl .= dirname($_SERVER['PHP_SELF']);
?>

(function($) {
  $(document).ready(function() {
    $("a.tab").click(function () {
      $(".active").removeClass("active");
      $(this).addClass("active");
      $(".content").slideUp();
      var content_show = $(this).attr("title");
      $("#"+content_show).slideDown();
      if (content_show=="content_3") go_check_version();
    });
  });
})(jQuery)

function go_swap_image(divid,imageid,albumid,func) {
	orig = document.getElementById("go-button-"+divid).src;
	document.getElementById("go-button-"+divid).src = "<?php echo $siteurl; ?>/../../images/go-working.gif";
	var data = { action: 'go_album_mod', go_func: func, imgid: imageid, albmid: albumid};
	jQuery.post(ajaxurl, data, function(response) {
//		alert('got this from the server: [' + response + ']');
		if (response==0) document.getElementById("go-button-"+divid).src = orig;
		else {
			if (func=="add") {
				document.getElementById("go-button-"+divid).src = "<?php echo $siteurl; ?>/../../images/remove.png";
				document.getElementById("go-button-"+divid).onclick=function(){go_swap_image(divid,imageid,albumid,'remove');};
			} else if (func=="remove") {
				document.getElementById("go-button-"+divid).src = "<?php echo $siteurl; ?>/../../images/add.png";
				document.getElementById("go-button-"+divid).onclick=function(){go_swap_image(divid,imageid,albumid,'add');};
			}
		}
	});
}

// The following function was written in Javascript, as opposed to directly loading
// it on the control page every single time.  This way, if WordPress is installed on
// a server that disallows outbound HTTP port 80 requests, it won't cause the page
// to freeze/hang up.  It will just display checking for updates, or fail.

function go_check_version() {
	var data = { action: 'go_check_for_updates' };
	jQuery.post(ajaxurl, data, function(odata) {
		document.getElementById("go-check-for-update").innerHTML = odata;
	}); 
}

function go_confirm_delete() {
	var agree = confirm("Are you sure you want to delete the Gallery Object?");
	return agree;
}

// The following function needs to be modified for other view types.
// Currently it only supports AD Gallery (1) view types.

function go_save_view_settings(thisform) {
	orig = document.getElementById("go-button-save").src;
	document.getElementById("go-button-save").src = "<?php echo $siteurl; ?>/../../images/go-working.gif";

	if (thisform.displaytitle.checked) displaytitle = 'true'; else displaytitle = 'false';
	if (thisform.displaydescription.checked) displaydescription = 'true'; else displaydescription = 'false';
	if (thisform.autostart.checked) autostart = 'true'; else autostart = 'false';
	if (thisform.transparentbackground.checked) transparentbackground = 'true'; else transparentbackground = 'false';
	if (thisform.descriptiontransparent.checked) descriptiontransparent = 'true'; else descriptiontransparent = 'false';

//	descriptionalign = 'bottom';
	descriptionalign = thisform.descriptionalign[thisform.descriptionalign.selectedIndex].value
//	for (var i=0; i<thisform.descriptionalign.length; i++) {
//		if (thisform.descriptionalign[i].checked) {
//			descriptionalign = thisform.descriptionalign[i].value;
//		}
//	}

	displaylinkfrom = 'none';
	for (var i=0; i<thisform.displaylinkfrom.length; i++) {
		if (thisform.displaylinkfrom[i].checked) {
			displaylinkfrom = thisform.displaylinkfrom[i].value;
		}
	}

	navlocation = 'bottom';
	for (var i=0; i<thisform.navlocation.length; i++) {
		if (thisform.navlocation[i].checked) {
			navlocation = thisform.navlocation[i].value;
		}
	}

	scrollcolor = 'white';
	for (var i=0; i<thisform.scrollcolor.length; i++) {
		if (thisform.scrollcolor[i].checked) {
			scrollcolor = thisform.scrollcolor[i].value;
		}
	}

	var data = {	 action: 'go_save_view_settings'
			,viewid: thisform.viewid.value
			,container_height: thisform.containerheight.value
			,container_width: thisform.containerwidth.value
			,container_background: thisform.containerbackground.value
			,transparent_background: transparentbackground
			,description_background: thisform.descriptionbackground.value
			,description_transparent: descriptiontransparent
			,thumbnail_height: thisform.thumbheight.value
			,trans_time: thisform.transtime.value
			,trans_speed: thisform.transspeed.value
			,trans_effect: thisform.transeffect[thisform.transeffect.selectedIndex].value
			,desc_align: descriptionalign
			,imagewrapper_valign: thisform.imagevalign[thisform.imagevalign.selectedIndex].value
			,display_title: displaytitle
			,display_description: displaydescription
			,display_link_from: displaylinkfrom
			,auto_start: autostart
			,nav_location: navlocation
			,scroll_color: scrollcolor
			,ctrl_font_color: thisform.controlfontcolor.value
			,ctrl_font_size: thisform.controlfontsize.value
			,ctrl_font_family: thisform.controlfontfamily.value
			,desc_font_color: thisform.descriptionfontcolor.value
			,desc_font_size: thisform.descriptionfontsize.value
			,desc_font_family: thisform.descriptionfontfamily.value
			,titl_font_color: thisform.titlefontcolor.value
			,titl_font_size: thisform.titlefontsize.value
			,titl_font_family: thisform.titlefontfamily.value
			,outside_desc_width: thisform.descriptionwidth.value
			,outside_desc_height: thisform.descriptionheight.value
		};

	jQuery.post(ajaxurl, data, function(response) {
		if (response==0) { alert('failed to save settings!'); }
		document.getElementById("go-button-save").src = "<?php echo $siteurl; ?>/../../images/save-settings.png";
	});


	return false;
}

function go_iframe_reload(thisform,id,source) {

	// Get custom variables to display in the preview iframe.
	container_height = thisform.containerheight.value;
	container_width = thisform.containerwidth.value;
	container_background = thisform.containerbackground.value;
	description_background = thisform.descriptionbackground.value;
	thumbnail_height = thisform.thumbheight.value;
	trans_time = thisform.transtime.value;
	trans_speed = thisform.transspeed.value;
	trans_effect = thisform.transeffect[thisform.transeffect.selectedIndex].value;
	ctrl_font_color = thisform.controlfontcolor.value;
	ctrl_font_size = thisform.controlfontsize.value;
	ctrl_font_family = thisform.controlfontfamily.value;
	desc_font_color = thisform.descriptionfontcolor.value;
	desc_font_size = thisform.descriptionfontsize.value;
	desc_font_family = thisform.descriptionfontfamily.value;
	titl_font_color = thisform.titlefontcolor.value;
	titl_font_size = thisform.titlefontsize.value;
	titl_font_family = thisform.titlefontfamily.value;

	iframe = document.getElementById(id);
	outsource = source + '&GOPREVIEW=1&CONTAINERHEIGHT=' + container_height + '&CONTAINERWIDTH=' + container_width + '&CONTAINERBACKGROUND=' + container_background + '&DESCRIPTIONBACKGROUND=' + description_background;
	if (thumbnail_height!="") outsource += "&THUMBHEIGHT=" + thumbnail_height;

	if (trans_time!="") outsource += "&TRANSTIME=" + (trans_time*1000);
	if (trans_speed!="") outsource += "&TRANSSPEED=" + (trans_speed*1000);
	if (trans_effect!="") outsource += "&TRANSEFFECT=" + trans_effect;
	if (thisform.displaytitle.checked) outsource += "&DISPLAYTITLE=true"; else outsource += "&DISPLAYTITLE=false";
	if (thisform.displaydescription.checked) outsource += "&DISPLAYDESCRIPTION=true"; else outsource += "&DISPLAYDESCRIPTION=false";
	if (thisform.autostart.checked) outsource += "&AUTOSTART=true"; else outsource += "&AUTOSTART=false";
	if (thisform.transparentbackground.checked) outsource += "&TRANSPARENTBACKGROUND=true"; else outsource += "&TRANSPARENTBACKGROUND=";
	if (thisform.descriptiontransparent.checked) outsource += "&DESCRIPTIONTRANSPARENT=true"; else outsource += "&DESCRIPTIONTRANSPARENT=";
	if (thisform.descriptionwidth.value!="") outsource += "&DESCRIPTIONWIDTH=" + thisform.descriptionwidth.value;
	if (thisform.descriptionheight.value!="") outsource += "&DESCRIPTIONHEIGHT=" + thisform.descriptionheight.value;

	if (ctrl_font_color!="")  outsource += "&CONTROLFONTCOLOR="  + ctrl_font_color;
	if (ctrl_font_size!="")   outsource += "&CONTROLFONTSIZE="   + ctrl_font_size;
	if (ctrl_font_family!="") outsource += "&CONTROLFONTFAMILY=" + ctrl_font_family;
	if (desc_font_color!="")  outsource += "&DESCRIPTIONFONTCOLOR="  + desc_font_color;
	if (desc_font_size!="")   outsource += "&DESCRIPTIONFONTSIZE="   + desc_font_size;
	if (desc_font_family!="") outsource += "&DESCRIPTIONFONTFAMILY=" + desc_font_family;
	if (titl_font_color!="")  outsource += "&TITLEFONTCOLOR="    + titl_font_color;
	if (titl_font_size!="")   outsource += "&TITLEFONTSIZE="     + titl_font_size;
	if (titl_font_family!="") outsource += "&TITLEFONTFAMILY="   + titl_font_family;

	outsource += "&DESCRIPTIONALIGN=" + thisform.descriptionalign[thisform.descriptionalign.selectedIndex].value
	outsource += "&IMAGEWRAPPERVALIGN=" + thisform.imagevalign[thisform.imagevalign.selectedIndex].value

//	for (var i=0; i<thisform.descriptionalign.length; i++) {
//		if (thisform.descriptionalign[i].checked) {
//			outsource += "&DESCRIPTIONALIGN=" + thisform.descriptionalign[i].value;
//		}
//	}

	for (var i=0; i<thisform.displaylinkfrom.length; i++) {
		if (thisform.displaylinkfrom[i].checked) {
			outsource += "&DISPLAYLINKFROM=" + thisform.displaylinkfrom[i].value;
		}
	}

	for (var i=0; i<thisform.navlocation.length; i++) {
		if (thisform.navlocation[i].checked) {
			outsource += "&NAVLOCATION=" + thisform.navlocation[i].value;
		}
	}

	for (var i=0; i<thisform.scrollcolor.length; i++) {
		if (thisform.scrollcolor[i].checked) {
			outsource += "&SCROLLCOLOR=" + thisform.scrollcolor[i].value;
		}
	}

	iframe.src = outsource;
//	alert(iframe.src);
//	iframe.contentDocument.location.reload(true);
	iframe.src = iframe.src;
}

function go_sh() {

	if (document.getElementById("go-as-1").style.display=='') {
		document.getElementById("go-as-1").style.display = 'none';
		document.getElementById("go-as-2").style.display = 'none';
		document.getElementById("go-as-3").style.display = 'none';
		document.getElementById("go-as-4").style.display = 'none';
		document.getElementById("go-as-5").style.display = 'none';
		document.getElementById("go-as-6").style.display = 'none';
		document.getElementById("go-as-7").style.display = 'none';
	} else {
		document.getElementById("go-as-1").style.display = '';
		document.getElementById("go-as-2").style.display = '';
		document.getElementById("go-as-3").style.display = '';
		document.getElementById("go-as-4").style.display = '';
		document.getElementById("go-as-5").style.display = '';
		document.getElementById("go-as-6").style.display = '';
		document.getElementById("go-as-7").style.display = '';
	}
}

