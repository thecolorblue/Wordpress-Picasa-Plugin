(function($){

// gallery/image switch by button #pe2-switch
var pe2_gallery=false;
// falg to album handler for insert shortcode instead of open album's images
var pe2_shortcode=false;
/* state of the code: reflect the header part displayed
 * 	nouser - input for user
 * 	albums - show albums
 *  images - show images from album
 */
var pe2_state='albums';
// picasa user name
var pe2_user_name = 'undefined';
// numbering 
var pe2_current=1;
// save the last request to the server for reload button
var pe2_last_request=false;
var pe2_no_request=false;
// cache for server request both albums and images 
var pe2_cache=[];
var pe2_options = {
    waiting     : 'loading image and text for waiting message',
    env_error   : 'error if editor function can not be found',
    image       : 'label for button Image',
    gallery     : 'label for button Gallery',
    reload      : 'label for button Reload',
    options     : 'label for button Reload',

    uniqid      : 'uniq id for gallery',

    thumb_w     : 150,      //'thumbnail width',
    thumb_h     : 0,        //'thumbnail height',
    thumb_crop  : false,    //'exact dimantions for thumbnail',

    state       : 'state by default or saved'
};

$(function() {
	// convert encoded options
	for ( var i in window['pe2_options'] ) {
		if (window['pe2_options'][i]=='0'||window['pe2_options'][i]=='') pe2_options[i]=false;
		else pe2_options[i] = decodeURIComponent(window['pe2_options'][i]);
		
	}
    
    // get username
    pe2_user_name = pe2_options.pe2_user_name;
    $('#pe2-user').text(pe2_user_name);
    // restore state
    if ('images' == pe2_options.state) {
        $('#pe2-albums').show().siblings('.pe2-header').hide();
        pe2_request({
            action: 'pe2_get_images',
            guid: pe2_options.pe2_last_album
        });
    } else {
        pe2_switch_state(pe2_options.state);
    }

    // set options unchanged handlers
    $('#pe2-options input').change(function() {
        var name = $(this).attr('name');
        if ('text' == $(this).attr('type')) {
            pe2_options[name] = $(this).val();
        } else {
            if ($(this).attr('checked'))
                pe2_options[name] = $(this).val();
            else
                pe2_options[name] = false;
        }
    });
    $('#pe2-options select').change(function() {
        var name = $(this).attr('name');
        pe2_options[name] = $(this).val();
    });
	// the form unchanged handler
	$('#pe2-nouser form').submit(pe2_change_user);
});

$(document).ajaxError(function(event, request, settings, error) {
    console.log("Error requesting page " + settings.url+'\nwith data: '+settings.data+'\n'+error);
});

function pe2_switch_state(state){
	pe2_state = state;
	$('#pe2-'+state).show().siblings('.pe2-header').hide();
	pe2_set_handlers();
}

function pe2_save_state(last_request) {
    if (pe2_options.pe2_save_state) {
        $.post(ajaxurl, {
            action: 'pe2_save_state',
            state:pe2_state,
            last_request:last_request
        });
    }
}

function pe2_set_handlers() {

	$('.button').unbind();
	
	$('.pe2-reload').click(function(){
		if (pe2_last_request) {
			if (pe2_state != 'albums') $('#pe2-albums').show().siblings('.pe2-header').hide();
			pe2_cache[pe2_serialize(pe2_last_request)] = false;
			pe2_request(pe2_last_request);
		}
		return(false);
	});

    $('.pe2-options').toggle(
        function(){
            $('#pe2-options').slideDown('fast');
            pe2_show_options();
            return(false);
        },function(){
            $('#pe2-options').slideUp('fast');
            pe2_show_options();
            // handle exceptions
            if (pe2_gallery && !(pe2_options.pe2_link == 'thickbox' || pe2_options.pe2_link == 'lightbox' || pe2_options.pe2_link == 'highslide')) {
                $('#pe2-switch').click();
            }
            return(false);
    });
    pe2_show_options();

	switch (pe2_state) {
		case 'nouser':
			$('#pe2-change-user').click(pe2_change_user);
			$('#pe2-cu-cancel').click(function() {
				pe2_switch_state('albums');
				return(false);
			});
			$('#pe2-main').empty();
		break;
		
		case 'albums':
			$('#pe2-user').click(function(){
				$('#pe2-nouser input').val(pe2_user_name);
				pe2_switch_state('nouser');
				return(false);
			});
			$('#pe2-switch2').click(function() {
                pe2_shortcode = pe2_shortcode?false:true;
                $(this).text((pe2_shortcode)?pe2_options.shortcode:pe2_options.album);
				return(false);
			});
            pe2_get_albums();
		break;
		
		case 'images':
			pe2_current=1;
			$('#pe2-switch').click(function() {
				if (pe2_gallery || pe2_options.pe2_link == 'thickbox' || pe2_options.pe2_link == 'lightbox' || pe2_options.pe2_link == 'highslide') {
					pe2_gallery = pe2_gallery?false:true;
					// if image unselect siblings and run click for the first
					if (!pe2_gallery) {
						$('#pe2-main td.selected').removeClass('selected').eq(0).addClass('selected');
						$('#pe2-main td div.numbers').remove();
					} else {
						pe2_current=1;
						$('#pe2-main td.selected').removeClass('selected').click();
					}
				}
                $(this).text((pe2_gallery)?pe2_options.gallery:pe2_options.image);
				return(false);
			});
			$('#pe2-album-name').click(function(){
				pe2_switch_state('albums');
				return(false);
			});
			$('#pe2-insert').click(function(){
                pe2_save_state(pe2_last_request.guid);
				// format and inserting the code into editor
				pe2_add_to_editor(pe2_make_html('#pe2-main td.selected'));
				return(false);
			}).hide();
		break;
	}
}

function pe2_change_user() {
	pe2_user_name = $('#pe2-nouser input').val();
	$('#pe2-user').text(pe2_user_name);
	pe2_switch_state('albums');
    pe2_save_state(pe2_user_name);
	return(false);
}

function pe2_request(data) {
	
	if (pe2_no_request) return;
	pe2_no_request = true;
	$('.pe2-reload').hide();
	
	pe2_last_request = data;
	var callback = (data.action=='pe2_get_gallery')?pe2_albums_apply:pe2_images_apply;
	
	if (pe2_cache[pe2_serialize(data)]) {
		callback(pe2_cache[pe2_serialize(data)]);
	} else {
		// set progress image
		$('#pe2-message2').html(pe2_options.waiting);	

		data['cache'] = pe2_serialize(data);
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post(ajaxurl, data, callback,'json');
	}	
}

function pe2_get_albums(){

	pe2_request({
		action: 'pe2_get_gallery',
		user: pe2_user_name
	});
}

function pe2_album_hanler(){

    var guid = $('a',this).attr('href').replace(/^[^#]*#/,'');

    if (pe2_shortcode) {
        pe2_save_state(pe2_user_name);
        pe2_add_to_editor('[pe2-gallery album="'+guid+'"]');
    } else {
        pe2_request({
            action: 'pe2_get_images',
            guid: guid
        });
    }
    
	return(false);
}

function pe2_show_reload() {
	pe2_no_request = false;
	$('.pe2-reload').show().text(pe2_options.reload);
}

function pe2_show_options() {
	$('.pe2-options').show().text(pe2_options.options);
}

function pe2_albums_apply(response) {
	
	pe2_show_reload();
	
	if (response.error) {
		$('#pe2-nouser input').val(pe2_user_name);
		pe2_switch_state('nouser');
		$('#pe2-message1').text(response.error);
		return;
	}

	pe2_cache[response.cache] = response;

	$('#pe2-main').html(response.data);
	$('#pe2-message2').text(response.title);
	document.body.scrollTop=0;

	$('#pe2-main td').unbind().click(pe2_album_hanler);
	// state switched before request
}

function pe2_images_apply(response) {
	
	pe2_show_reload();
	
	if (response.error) {
		$('#pe2-message2').text(response.error);
		return;
	}

	pe2_cache[response.cache] = response;

	$('#pe2-main').html(response.data);
	$('#pe2-album-name').text(response.title);
	document.body.scrollTop=0;

	$('#pe2-main td').unbind().click(pe2_image_hanler);
	pe2_switch_state('images');
}
	
function pe2_image_hanler(){
	
	if (!pe2_gallery) $('#pe2-main td').removeClass('selected');
		
	if (pe2_options.pe2_gal_order && pe2_gallery) {
		if ($(this).hasClass('selected')) {
			var current = Number($('div.numbers',this).html());
			$('div.numbers',this).remove();
			// decrement number for rest if >current
			$('#pe2-main td.selected').each(function(){
				var i = Number($('div.numbers',this).html());
				if (i>current) $('div.numbers',this).html(i-1);
			});
			pe2_current--;
		} else {
			$(this).prepend("<div class='numbers'>"+pe2_current+"</div>");
			pe2_current++;
		}
	}
	
	$(this).toggleClass('selected');
	// check selected to show/hide Insert button
	if ($('#pe2-main td.selected').length==0) $('#pe2-insert').hide();
	else $('#pe2-insert').show();
	
	return(false);
}

function pe2_serialize(data) {
	function Dump(d,l) {
	    if (l == null) l = 1;
	    var s = '';
	    if (typeof(d) == "object") {
	        s += typeof(d) + " {\n";
	        for (var k in d) {
	            for (var i=0; i<l; i++) s += "  ";
	            s += k+": " + Dump(d[k],l+1);
	        }
	        for (var i=0; i<l-1; i++) s += "  ";
	        s += "}\n";
	    } else {
	        s += "" + d + "\n";
	    }
	    return s;
	}
	return Dump(data);
}

function pe2_add_to_editor(data) {
	var win = window.dialogArguments || opener || parent || top;
	if (win['send_to_editor']) win.send_to_editor(data);
	else {
		alert(pe2_options.env_error);
		tb_remove();
	}
}

String.prototype.trim = function() {
    var s=this.toString().split('');
    for (var i=0;i<s.length;i++) if (s[i]!=' ') break;
    for (var j=s.length-1;j>=i;j--) if (s[j]!=' ') break;
    return this.substring(i,j+1);
}

String.prototype.escape = function() {
    var s = this.toString();
    s = s.replace(/&/g, "&amp;");
    s = s.replace(/>/g, "&gt;");
    s = s.replace(/</g, "&lt;");
    s = s.replace(/"/g, "&quot;");
    s = s.replace(/'/g, "&#039;");
    return s;
}

function pe2_make_html(case_selector) {

	var images=[], img, icaption, ialbum, isrc, ialt, ititle, iorig;

	// prepare common image attributes
	var iclass = [pe2_options.pe2_img_css || ''];
	var istyle = [pe2_options.pe2_img_style || ''];

	// create align vars
	// for caption - align="alignclass" including alignnone also
	// else add alignclass to iclass
	var calign = '';
	if (pe2_options.pe2_caption) {
		calign = 'align="align'+pe2_options.pe2_img_align+'" ';
	} else {
		iclass.push('align'+pe2_options.pe2_img_align);
	}

	// check thumb setting and define width or height or both
	var idimen   = [
        (pe2_options.thumb_w && 'width="'+pe2_options.thumb_w+'"') || '',
        (pe2_options.thumb_h && 'height="'+pe2_options.thumb_h+'"') || ''
    ];
	if (!pe2_options.thumb_crop && idimen[0]) delete idimen[1];
    // new size for thumbnail
    var new_thumb_size = '';
    if (pe2_options.thumb_w && pe2_options.thumb_h) {
        // both sizes and crop
        if (pe2_options.thumb_w == pe2_options.thumb_h) {
            if (pe2_options.thumb_crop) new_thumb_size = '/s'+pe2_options.thumb_w+'-c';
            else new_thumb_size = '/s'+pe2_options.thumb_w;
        }
        else if (pe2_options.thumb_crop && pe2_options.thumb_w == pe2_options.thumb_h) new_thumb_size = '/s'+pe2_options.thumb_w+'-c';
        else if (pe2_options.thumb_w > pe2_options.thumb_h) new_thumb_size = '/w'+pe2_options.thumb_w;
        else new_thumb_size = '/h'+pe2_options.thumb_h;
    }
    else if (pe2_options.thumb_w) new_thumb_size = '/w'+pe2_options.thumb_w;
    else if (pe2_options.thumb_h) new_thumb_size = '/h'+pe2_options.thumb_h;
    // new size for large image
    var new_large_size='';
    if (pe2_options.pe2_large_limit) new_large_size = '/'+pe2_options.pe2_large_limit;

    var cdim  = (pe2_options.thumb_w && 'width="'+pe2_options.thumb_w+'" ') || '';

	// link and gallery additions
	var amore='';
	switch (pe2_options.pe2_link) {
		case 'thickbox':
			amore = 'class="thickbox" ';
			if (pe2_gallery) amore += 'rel="'+pe2_options.uniqid+'" ';
			break;
		case 'lightbox':
			amore = (pe2_gallery)?'rel="lightbox-'+pe2_options.uniqid+'" ':'rel="lightbox" ';
			break;
		case 'highslide':
			amore = (pe2_gallery)?'class="highslide" onclick="return hs.expand(this,{ slideshowGroup: \''+pe2_options.uniqid+'\' })"':
				'class="highslide" onclick="return hs.expand(this)"';
			break;
	}

	// selection order
	var order = (pe2_options.pe2_gal_order && pe2_gallery);

    iclass = iclass.join(' ').trim();iclass = (iclass && 'class="'+iclass+'" ') || '';
    istyle = istyle.join(' ').trim();istyle = (istyle && 'style="'+istyle+'" ') || '';
    idimen = idimen.join(' ').trim();idimen = (idimen && idimen+' ') || '';

	$(case_selector).each(function(i){
		icaption = $('span',this).text().escape(); // ENT_QUOTES
		ialbum   = $('a',this).attr('href');
		isrc     = $('img',this).attr('src');
		iorig    = isrc.replace(/\/[swh]\d+(\/[^\/]+)$/,new_large_size+'$1');
		isrc     = isrc.replace(/\/[swh]\d+(\/[^\/]+)$/,new_thumb_size+'$1');
		ialt     = $('img',this).attr('alt');
		ititle   = (pe2_options.pe2_title && 'title="'+icaption+'" ') || '';

		img = '<img src="%src%" alt="%alt%" %more%/>'.replace(/%(\w+)%/g,function($0,$1) {
            switch ($1) {
                case 'src':return isrc;
                case 'alt':return ialt;
                case 'more':
                    return ititle+iclass+istyle+idimen;
            }
        });

        if (pe2_options.pe2_link != 'none') {
			if (pe2_options.pe2_link == 'picasa') iorig = ialbum;
			img = '<a href="'+iorig+'" '+ititle+amore+'>'+img+'</a>';
		}
		if (pe2_options.pe2_caption) {
			// add caption
			img = '[caption id="" '+calign+cdim+'caption="'+icaption+'"]'+img+'[/caption]';
		}
		if (order) {
			images[Number($('div.numbers',this).html())] = img;
		} else {
			images.push(img);
		}

	});

	if (pe2_gallery) {

		images = images.join('');

        var gal_css = [pe2_options.pe2_gal_css || '',((pe2_options.pe2_gal_align!='none') && 'align'+pe2_options.pe2_gal_align) || ''].join(' ').trim();

        images = '[pe2-gallery%css_style%]\n%images%[/pe2-gallery]'.replace(/%(\w+)%/g,function($0,$1){
            switch ($1) {
                case 'css_style':
                    var a = [(gal_css && 'class="'+gal_css+'"') || '',(pe2_options.pe2_gal_style && 'style="'+pe2_options.pe2_gal_style+'"') || ''].join(' ').trim();
                    return (a && ' '+a+' ');
                case 'images':
                    return images;
            }
        });
	} else {
        images = images.join('')+' ';
    }

    return images;
}


})(jQuery);
