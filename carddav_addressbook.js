if (window.rcmail)
{
	rcmail.addEventListener('init', function(evt)
	{
	    rcmail.register_command('plugin.carddav_addressbook_sync', carddav_addressbook_sync, rcmail.env.uid);
        rcmail.enable_command('plugin.carddav_addressbook_sync', true);
		
//		rcmail.register_command('plugin.carddav_addressbook_sync', function()
//		{
//			alert('yay!');
//			var input_label = rcube_find_object('_label');
//			var input_url = rcube_find_object('_server_url');
//			var input_username = rcube_find_object('_username');
//			var input_password = rcube_find_object('_password');
//			
//			if (input_label.value == '' || input_url.value == '' || input_username.value == '' || input_password.value == '')
//			{
//				rcmail.display_message(rcmail.gettext('settings_empty_values', 'carddav'), 'error');
//			}
//			else
//			{
//				rcmail.http_post('plugin.carddav-server-check', '_server_url=' + input_url.value + '&_username=' + input_username.value + '&_password=' + input_password.value);
//			}
//		}, true);
		
	});
}

function carddav_addressbook_sync(prop)
{
	alert('callback!');
}