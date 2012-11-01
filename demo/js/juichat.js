var _mydata;
$.fn.livechat = function(options) {

	var settings = $.extend({
		recheckdelay: 6000,
		idletime: 180,
		getViewers: false,
		emoticons: true,
		page: 'juichat.php',
		viewerwrapper: '.ui-lc-viewers'
	}, options);


	var _flashstatus=false;
	var _heartbeat=false;
	var _forceautoscroll=false;

	Construct();
	
	function Construct(){
		// container for chat dialogs
		if($('body').find('.ui-lc-container').length == 0) $('<div/>').addClass('ui-lc-container').appendTo('body');

		// construct the chats and set user defaults
		$.getJSON(settings.page, { mode: 'SetNewVisit' },
			function(data){
				_mydata=data;
				_forceautoscroll=true;
				fnParseChats(data.chat)
				if(settings.getViewers) fnParseViewers(data.viewers);

				setInterval(function() {
					var _heartbeat=true;
					_flashstatus=true;
					_forceautoscroll=false;
					fnChatHeartBeat();
				}, settings.recheckdelay);
			}
		);
	}

	function fnSetName(){
		var _user='Your Name';
		var _email='Your E-mail';

		if(_mydata.user != 'Unknown') _user=_mydata.user;
		if(_mydata.email != 'Unknown') _email=_mydata.email;

		$( '<div/>' ).dialog({
			title:'Profile',
			buttons: [ {
				id: "dialogSave",
				text: 'Save',
				click: function() { 
					$.get(settings.page, { mode: 'SetUserName', name:$('#user_name').val(), email:$('#user_email').val() } );
					_mydata.user=$('#user_name').val();
					_mydata.email=$('#user_email').val();
					$( this ).dialog( "close" );
				}
			},{
				id: "dialogCancel",
				text: 'Cancel',
				click: function(){$( this ).dialog( "close" )}
			}]				
		}).html('<input value="'+_user+'" id="user_name"/><input value="'+_email+'" id="user_email"/>');
	}

	function fnRenderGravitar(gravitar){
		return '<div class="ui-lc-gravitar"><img src="http://www.gravatar.com/avatar/'+gravitar+'?d=mm"/></div>';
	}

	function fnParseViewers(data){
		$('.ui-lc-viewer-container, .ui-lc-viewer-containerheader').remove();
		$('<div/>').addClass('ui-widget-header ui-lc-viewer-containerheader').appendTo(settings.viewerwrapper);
		$('<div/>').addClass('ui-lc-viewer-containerheader-txt').html(fnRenderGravitar(_mydata.gravitar) + ' ' +_mydata.user).appendTo('.ui-lc-viewer-containerheader');
		$('<span/>').addClass('ui-icon ui-icon-gear ui-pointer').click(function(){fnSetName()}).appendTo('.ui-lc-viewer-containerheader');

		$('<ul/>').addClass('ui-lc-viewer-container ui-widget').appendTo(settings.viewerwrapper);

		$.each(data, function(i, item){
			var _row=$('<li/>', {title: 'Browser: '+ item.browser +'\nIP:'+item.ip}).html(fnRenderGravitar(item.gravitar));
			var _button=$('<div/>', {'class': 'ui-lc-viewers-user'}).html(item.user).click(function(){
				// reset defaults
				item.minimized='false';
				item.closed='false';

				// start chat = resets minimied, closed and inserts empty row to initiate dialog
				$.get(settings.page, { mode: 'StartChat', hash:item.hash, positions: fnGetPositions()});

				// open chat
				_flashstatus=false;
				fnCheckChat(item, true);
				_flashstatus=true;
				return false;
			}).appendTo(_row);

			$('<div/>', {'class': 'ui-helper-clearfix'}).appendTo(_row);

			_row.appendTo('.ui-lc-viewer-container');
		});
	}


	function fnGetPositions(){
		var positions=new Array();
		var _position=$('.ui-lc-container').find('.ui-lc-chatwindow:visible');
		$.each(_position, function(i, item){
			var _res=new Array(2);
			_res[0]=item.id;
			_res[1]=$('#'+item.id).index();
			positions[i]=_res;
		});
		return positions
	}

	function fnChatHeartBeat(){
		$.getJSON(settings.page, { mode: 'GetChats' },
			function(data){
				fnParseChats(data.chats);
				if(settings.getViewers) fnParseViewers(data.viewers);
			}
		);
	}

	function fnParseChats(data){
		$.each(data, function(i, item){fnCheckChat(item)});
	}

	function fnCheckChat(result, positions){
		if($('.ui-lc-container').find('#'+result.hash).length == 0){
			if(result.closed != 'true'){
				// dont show empty chats from caller!
				if(result.receiver == _mydata.hash && result.chat == '') return false;
				fnOpenChat(result.user, result.hash, result.chat, result.minimized);
			}
			fnUpdateChat(result.hash, result.chat, result.online, result.lastmsg);
		} else {
			if(result.closed != 'true')$('.ui-lc-container').find('#'+result.hash).show();
			if(result.minimized != 'true'){
				$('.ui-lc-container').find('#'+result.hash).find('.ui-lc-chatcontents, .ui-lc-chatinput').show();
				$('.ui-lc-container').find('#'+result.hash).css({'margin-top': '0px', 'width':'240px'});
			}
			$('#'+result.hash).find('.ui-lc-chatheader-user').html(result.user);
			fnUpdateChat(result.hash, result.chat, result.online, result.lastmsg);
		}
	}

	function fnEmoticonize(_div){
		_div.find('.ui-lc-chattxt').emoticonize({animate:false});
	}

	function fnSetChatRow(hash,_chatbox, _chat){
		if(_chat.chat !=''){
			if(!_chat.time) _chat.time='now';
			var _row=$('<div/>').addClass('ui-lc-chatentry').mouseover(function(){
				_row.find('.ui-lc-chatdate-txt').show();
			}).mouseout(function(){
				_row.find('.ui-lc-chatdate-txt').hide();
			});
			$('<div/>').addClass('ui-lc-gravitar').html(fnRenderGravitar(_chat.gravitar)).appendTo(_row);
			var _chatdate=$('<div/>').addClass('ui-lc-chatdate').appendTo(_row);
			$('<div/>').addClass('ui-lc-chatdate-txt').html(_chat.time).appendTo(_chatdate);
			$('<div/>').addClass('ui-lc-chattxt').html(_chat.chat).appendTo(_row);
			$('<div/>').addClass('ui-helper-clearfix').appendTo(_row);
			_chatbox.append(_row);
		}
	}

	function fnUpdateChat(hash, chat, online, lastmsg, mymsg){
		var _chatbox=$('#'+hash).find('.ui-lc-chatcontents');
		if(_chatbox.length == 0) return false;
		
		var _autoscroll=false;
		if(_chatbox.scrollTop()+_chatbox.height() == _chatbox[0].scrollHeight) _autoscroll=true;

		if(chat) $.each(chat, function(i, item){fnSetChatRow(hash,_chatbox, jQuery.parseJSON(item))});

		if(_autoscroll || _forceautoscroll) _chatbox.scrollTop(_chatbox[0].scrollHeight);

		if(settings.emoticons) fnEmoticonize(_chatbox);

		if(chat != '' && _flashstatus == true) $('#'+hash).find('.ui-lc-statusnotify').effect("pulsate", { opacity: 0.1, times:3 }, 3000);
 		if(online == false){
			$('#'+hash).find('.ui-lc-userstatus').html('User is offline').show();
			$('#'+hash).find('.ui-lc-statusnotify').css({'background':'red'});
		} else {
			$('#'+hash).find('.ui-lc-userstatus').html('').hide();
			if(lastmsg > settings.idletime){
				$('#'+hash).find('.ui-lc-statusnotify').css({'background':'#EEEE00'});
			} else $('#'+hash).find('.ui-lc-statusnotify').css({'background':'green'});
		}
	}

	function fnStripTags(input, allowed) {
       allowed = (((allowed || "") + "").toLowerCase().match(/<[a-z][a-z0-9]*>/g) || []).join(''); 
       var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi,commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi;
       return input.replace(commentsAndPhpTags, '').replace(tags, function($0, $1){
          return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : '';
       });
    }

	function SendChat(hash, chat){
		if(chat){
			chat=fnStripTags(chat, '<i>');
			chat=chat.replace(/\n/g,'<br />');
			var _subdata={"chat" : chat, "hash" : _mydata.hash,  "user" : _mydata.user, "email" : _mydata.email, "gravitar" : _mydata.gravitar };
			var data = { "0" : JSON.stringify(_subdata)};
			_flashstatus=false;
			fnUpdateChat(hash, data);
			_flashstatus=true;

			var _chatbox=$('#'+hash).find('.ui-lc-chatcontents');
			_chatbox.scrollTop(_chatbox[0].scrollHeight);

		}
		$.get(settings.page, { mode: 'SendChat', hash:hash, chat:chat});
	}

	function fnOpenChat(user, hash, chat, minimized){
		var _chatwindow=$( "<div/>" )
			.attr('id', hash)
			.addClass( "ui-lc-chatwindow ui-widget ui-widget-content ui-helper-clearfix ui-corner-top" )
			.css({'float': 'right'});

		$('<div/>').click(function(){ fnToggleMinimize(_chatwindow, hash); return false; }).addClass( "ui-lc-header ui-widget-header ui-corner-top ui-helper-clearfix" ).appendTo(_chatwindow);

		$('<div/>', {'class':'ui-lc-statusnotify ui-corner-all'}).prependTo(_chatwindow.find('.ui-lc-header'));

		var _chatheader=$('<div/>').appendTo(_chatwindow.find('.ui-lc-header'));
		$('<div/>', {'class':'ui-lc-chatheader-user'}).html(user).appendTo(_chatheader);


		$('<span/>').addClass('ui-icon ui-icon-close ui-pointer').click(function(){ 
			$(_chatwindow).hide();
			fnCloseChat(hash, true);
			return false;
		}).appendTo(_chatwindow.find('.ui-lc-header'));

		$('<div/>', {'class': 'ui-lc-userstatus'}).prependTo(_chatwindow);

		$('<div/>').addClass( "ui-lc-chatcontents" ).appendTo(_chatwindow);

		$('<div/>')
				.addClass( "ui-lc-chatinput" )
				.html(
		$('<textarea/>')
			.addClass('ui-corner-bottom')
			.blur(function(){fnAutoSizeTextArea(_chatwindow)})
			.keydown(function(e) {
				fnAutoSizeTextArea(_chatwindow);
				if (e.keyCode == 13 && e.shiftKey) {
					return true
				} else if(e.which == 13) {
					_result=this.value;
					if(_result) SendChat(hash, _result);
					this.value='';
					fnAutoSizeTextArea(_chatwindow);
					return false;
				}
			})
		).appendTo(_chatwindow);

		$(_chatwindow).appendTo('.ui-lc-container');
		_chatwindow.find('textarea').focus();

		if(minimized == 'true'){
			_chatwindow.find('.ui-lc-chatcontents, .ui-lc-chatinput').hide();
			fnSetMinimized(_chatwindow, true);
		}
		
		// update with chat 
		if(_heartbeat) fnChatHeartBeat();
	
	}

	function fnCloseChat(hash, option){
		$.get(settings.page, { mode: 'CloseChat', hash:hash, option:option, positions: fnGetPositions() } );
	}

	function fnMinimizeChat(hash, option){
		$.get(settings.page, { mode: 'MinimizeChat', hash:hash, option:option } );
	}

	function fnSetMinimized(_chatwindow, option){
		if(option == true){
			_chatwindow.css({'margin-top': '201px', 'width': '140px'});
			_chatwindow.find('.ui-lc-chatheader-user').css({'width': '97px'});
		} else {
			_chatwindow.css({'margin-top': '0px', 'width': '240px'});
			_chatwindow.find('.ui-lc-chatheader-user').css({'width': '195px'});
		}
	}

	function fnToggleMinimize(_chatwindow, hash){
		_chatwindow.find('.ui-lc-chatcontents, .ui-lc-chatinput').toggle();
		var option=_chatwindow.find('.ui-lc-chatcontents').is(":hidden"); 
		fnSetMinimized(_chatwindow, option);
		fnMinimizeChat(hash, option);
		var _chatbox=$('#'+hash).find('.ui-lc-chatcontents');
		_chatbox.scrollTop(_chatbox[0].scrollHeight);
	}

	function fnAutoSizeTextArea(_chatwindow){
		var text = _chatwindow.find('.ui-lc-chatinput textarea').val();
		var matches = text.match(/\n/g);
		var breaks = matches ? matches.length : 0;
		
		// can be improved im sure! grow 1 line at a time, bout 26 chars max of 4-5 lines or so
		if(text.length < 26){
			_chatwindow.find('.ui-lc-chatcontents').height('176');
			_chatwindow.find('.ui-lc-chatinput textarea').height('22');
			 if(breaks == '1' || breaks == '2'){
				_chatwindow.find('.ui-lc-chatcontents').height('162');
				_chatwindow.find('.ui-lc-chatinput textarea').height('34');
			} else if(breaks > 2 ){
				_chatwindow.find('.ui-lc-chatcontents').height('110');
				_chatwindow.find('.ui-lc-chatinput textarea').height('90');
			}
		} else if(text.length > 26 && text.length < 52){
			_chatwindow.find('.ui-lc-chatcontents').height('162');
			_chatwindow.find('.ui-lc-chatinput textarea').height('34');
			if(breaks > 2 ){
				_chatwindow.find('.ui-lc-chatcontents').height('110');
				_chatwindow.find('.ui-lc-chatinput textarea').height('90');
			}
		} else if(text.length > 52 && text.length < 84){
			_chatwindow.find('.ui-lc-chatcontents').height('152');
			_chatwindow.find('.ui-lc-chatinput textarea').height('44');
		} else if(text.length > 84){
			_chatwindow.find('.ui-lc-chatcontents').height('110');
			_chatwindow.find('.ui-lc-chatinput textarea').height('90');
		}
	}

};