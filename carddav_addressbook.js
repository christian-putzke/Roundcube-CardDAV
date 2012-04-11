/**
 * Roundcube CardDAV addressbook extension
 *
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Christian Putzke @ Graviox Studios
 * @since 22.09.2011
 * @link http://www.graviox.de/
 * @link https://twitter.com/graviox/
 * @version 0.5.1
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */

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