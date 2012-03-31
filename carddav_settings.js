/**
 * Roundcube CardDAV addressbook extension
 *
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Christian Putzke @ Graviox Studios
 * @since 12.09.2011
 * @link http://www.graviox.de/
 * @link https://twitter.com/graviox/
 * @version 0.5
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */

if (window.rcmail)
{
	rcmail.addEventListener('init', function(evt)
	{
		var tab = $('<span>').attr('id', 'settingstabplugincarddav-server').addClass('tablink');

		var button = $('<a>').attr('href', rcmail.env.comm_path+'&_action=plugin.carddav-server').
			html(rcmail.gettext('settings_tab', 'carddav')).appendTo(tab);
		
		rcmail.add_element(tab, 'tabs');
		rcmail.addEventListener('plugin.carddav_server_message', carddav_server_message);

		rcmail.register_command('plugin.carddav-server-save', carddav_server_add, true);

		rcmail.register_command('plugin.carddav-server-delete', function(carddav_server_id)
		{
			rcmail.http_post(
				'plugin.carddav-server-delete',
				'_carddav_server_id=' + $.base64Encode(carddav_server_id),
				rcmail.display_message(rcmail.gettext('settings_delete_loading', 'carddav'), 'loading')
			);
		}, true);

		$('#_label').keypress(carddav_server_add_enter_event);
		$('#_server_url').keypress(carddav_server_add_enter_event);
		$('#_username').keypress(carddav_server_add_enter_event);
		$('#_password').keypress(carddav_server_add_enter_event);
	});

	function carddav_server_add_enter_event(e)
	{
		if (e.keyCode == 13)
		{
			carddav_server_add();
		}
	};

	function carddav_server_add()
	{
		var input_label		= rcube_find_object('_label');
		var input_url		= rcube_find_object('_server_url');
		var input_username	= rcube_find_object('_username');
		var input_password	= rcube_find_object('_password');
		var input_read_only	= rcube_find_object('_read_only');

		if (input_label.value == '' || input_url.value == '')
		{
			rcmail.display_message(rcmail.gettext('settings_empty_values', 'carddav'), 'error');
		}
		else
		{
			rcmail.http_post(
				'plugin.carddav-server-save',
				'_label=' + $.base64Encode(input_label.value) + '&_server_url=' + $.base64Encode(input_url.value) + '&_username=' + $.base64Encode(input_username.value) + '&_password=' + $.base64Encode(input_password.value)  + '&_read_only=' + $.base64Encode(input_read_only.value),
				rcmail.display_message(rcmail.gettext('settings_init_server', 'carddav'), 'loading')
			);
		}
	}

	function carddav_server_message(response)
	{
		if (response.check)
		{
			$('#carddav_server_list').hide();
			$('#carddav_server_list').html(response.server_list)
			$('#carddav_server_list').show('normal');
			
			rcmail.display_message(response.message, 'confirmation');
		}
		else
		{
			rcmail.display_message(response.message, 'error');
		}
	}
}