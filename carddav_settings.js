if (window.rcmail)
{
	rcmail.addEventListener('init', function(evt)
	{
		var tab = $('<span>').attr('id', 'settingstabplugincarddav-server').addClass('tablink');

		var button = $('<a>').attr('href', rcmail.env.comm_path+'&_action=plugin.carddav-server').
			html(rcmail.gettext('settings_tab', 'carddav')).appendTo(tab);
		
		rcmail.add_element(tab, 'tabs');
		rcmail.addEventListener('plugin.carddav_server_message', carddav_server_message);
		
		rcmail.register_command('plugin.carddav-server-save', function()
		{
			var input_label = rcube_find_object('_label');
			var input_url = rcube_find_object('_server_url');
			var input_username = rcube_find_object('_username');
			var input_password = rcube_find_object('_password');
			
			if (input_label.value == '' || input_url.value == '' || input_username.value == '' || input_password.value == '')
			{
				rcmail.display_message(rcmail.gettext('settings_empty_values', 'carddav'), 'error');
			}
			else
			{
				rcmail.display_message(rcmail.gettext('settings_init_server', 'carddav'), 'loading');
				rcmail.http_post('plugin.carddav-server-save', '_label=' + input_label.value + '&_server_url=' + input_url.value + '&_username=' + input_username.value + '&_password=' + input_password.value);
			}
		}, true);
	});
	
	function carddav_server_message(response)
	{
		if (response.check)
		{
			rcmail.display_message(response.message, 'confirmation');
		}
		else
		{
			rcmail.display_message(response.message, 'error');
		}
	}
}