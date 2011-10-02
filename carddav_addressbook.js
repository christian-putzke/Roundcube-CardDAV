if (window.rcmail)
{
	rcmail.addEventListener('init', function(evt)
	{
		rcmail.enable_command('plugin.carddav-addressbook-sync', true);
		rcmail.addEventListener('plugin.carddav_addressbook_message', carddav_addressbook_message);
		
		rcmail.register_command('plugin.carddav-addressbook-sync', function()
		{
			rcmail.http_post(
				'plugin.carddav-addressbook-sync',
				'',
				rcmail.display_message(rcmail.gettext('addressbook_sync_loading', 'carddav'), 'loading')
			);
		}, true);
	});
	
	function carddav_addressbook_message(response)
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