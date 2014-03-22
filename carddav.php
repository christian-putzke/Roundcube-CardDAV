<?php

/**
 * Include required CardDAV classes
 */
require_once dirname(__FILE__) . '/carddav_backend.php';
require_once dirname(__FILE__) . '/carddav_addressbook.php';

/**
 * Roundcube CardDAV implementation
 *
 * This is a CardDAV implementation for roundcube 0.6 or higher. It allows every user to add
 * multiple CardDAV server in their settings. The CardDAV contacts (vCards) will be synchronized
 * automaticly with their addressbook.
 *
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Christian Putzke @ Graviox Studios
 * @since 06.09.2011
 * @link http://www.graviox.de/
 * @link https://twitter.com/graviox/
 * @version 0.5.1
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */

class carddav extends rcube_plugin
{
	/**
	 * Roundcube CardDAV version
	 *
	 * @constant string
	 */
	const VERSION = '0.5.1';

	/**
	 * Tasks where the CardDAV plugin is loaded
	 *
	 * @var string
	 */
	public $task = 'settings|addressbook|mail';

	/**
	 * CardDAV addressbook
	 *
	 * @var string
	 */
	protected $carddav_addressbook = 'carddav_addressbook';

	/**
	 * Init CardDAV plugin - register actions, include scripts, load texts, add hooks
	 *
	 * @return	void
	 */
	public function init()
	{
		$rcmail		= rcmail::get_instance();
		$skin_path	= $this->local_skin_path();

		if (!is_dir($skin_path))
		{
			$skin_path = 'skins/default';
		}

		$this->add_texts('localization/', true);
		$this->include_stylesheet($skin_path . '/carddav.css');

		switch ($rcmail->task)
		{
			case 'settings':
				$this->register_action('plugin.carddav-server', array($this, 'carddav_server'));
				$this->register_action('plugin.carddav-server-save', array($this, 'carddav_server_save'));
				$this->register_action('plugin.carddav-server-delete', array($this, 'carddav_server_delete'));
				$this->include_script('carddav_settings.js');
				$this->include_script('jquery.base64.js');
				$this->add_hook('addressbooks_list', array($this, 'get_carddav_addressbook_sources'));
			break;

			case 'addressbook':
				if ($this->carddav_server_available())
				{
					$this->register_action('plugin.carddav-addressbook-sync', array($this, 'carddav_addressbook_sync'));
					$this->include_script('carddav_addressbook.js');
					$this->add_hook('addressbooks_list', array($this, 'get_carddav_addressbook_sources'));
					$this->add_hook('addressbook_get', array($this, 'get_carddav_addressbook'));

					$this->add_button(array(
						'command' => 'plugin.carddav-addressbook-sync',
						'imagepas' => $skin_path . '/sync_pas.png',
						'imageact' => $skin_path . '/sync_act.png',
						'width' => 32,
						'height' => 32,
						'title' => 'carddav.addressbook_sync'
					), 'toolbar');
				}
			break;

			case 'mail':
				if ($this->carddav_server_available())
				{
					$this->add_hook('addressbooks_list', array($this, 'get_carddav_addressbook_sources'));
					$this->add_hook('addressbook_get', array($this, 'get_carddav_addressbook'));

					$sources = (array) $rcmail->config->get('autocomplete_addressbooks', array('sql'));
					$servers = $this->get_carddav_server();

					foreach ($servers as $server)
					{
						if (!in_array($this->carddav_addressbook . $server['carddav_server_id'], $sources))
						{
							$sources[] = $this->carddav_addressbook . $server['carddav_server_id'];
							$rcmail->config->set('autocomplete_addressbooks', $sources);
						}
					}
				}
			break;
		}
	}

	/**
	 * Extend the original local_skin_path method with the default skin path as fallback
	 *
	 * @param	boolean		$include_plugins_directory	Include plugins directory
	 * @return	string		$skin_path					Roundcubes skin path
	 */
	public function local_skin_path($include_plugins_directory = false)
	{
		$skin_path	= parent::local_skin_path();

		if (!is_dir($skin_path))
		{
			$skin_path = 'skins/default';
		}

		if ($include_plugins_directory === true)
		{
			$skin_path = 'plugins/carddav/' . $skin_path;
		}

		return $skin_path;
	}

	/**
	 * Get all CardDAV servers from the current user
	 *
	 * @param	boolean	$carddav_server_id	CardDAV server id to load a single CardDAV server
	 * @return	array						CardDAV server array with label, url, username, password (encrypted)
	 */
	public function get_carddav_server($carddav_server_id = false)
	{
		$servers	= array();
		$rcmail		= rcmail::get_instance();
		$user_id	= $rcmail->user->data['user_id'];

		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_server')."
			WHERE
				user_id = ?
			".($carddav_server_id !== false ? " AND carddav_server_id = ?" : null)."
		";

		$result = $rcmail->db->query($query, $user_id, $carddav_server_id);

		while($server = $rcmail->db->fetch_assoc($result))
		{
			$servers[] = $server;
		}

		return $servers;
	}

	/**
	* Render available CardDAV server list in HTML
	*
	* @return	string	HTML rendered CardDAV server list
	*/
	protected function get_carddav_server_list()
	{
		$servers	= $this->get_carddav_server();

		if (!empty($servers))
		{
			$skin_path	= $this->local_skin_path(true);
			$content	= html::div(array('class' => 'carddav_headline'), $this->gettext('settings_server'));
			$table		= new html_table(array(
				'cols'	=> 6,
				'class'	=> 'carddav_server_list'
			));

			$table->add_header(array('width' => '13%'), $this->gettext('settings_label'));
			$table->add_header(array('width' => '36%'), $this->gettext('server'));
			$table->add_header(array('width' => '13%'), $this->gettext('username'));
			$table->add_header(array('width' => '13%'), $this->gettext('password'));
			$table->add_header(array('width' => '13%'), $this->gettext('settings_read_only'));
			$table->add_header(array('width' => '13%'), null);

			foreach ($servers as $server)
			{
				// $rcmail->output->button() seems not to work within ajax requests so we build the button manually
				$delete_submit = '<input
					type="button"
					value="'.$this->gettext('delete').'"
					onclick="return rcmail.command(\'plugin.carddav-server-delete\', \''.$server['carddav_server_id'].'\', this)"
					class="button mainaction"
				/>';

				$table->add(array(), $server['label']);
				$table->add(array(), $server['url']);
				$table->add(array(), $server['username']);
				$table->add(array(), '**********');
				$table->add(array(), ($server['read_only'] ? html::img($skin_path . '/checked.png') : null));
				$table->add(array(), $delete_submit);
			}

			$content .= html::div(array('class' => 'carddav_container'), $table->show());
			return $content;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Get CardDAV addressbook instance
	 *
	 * @param	array	$addressbook	Array with all available addressbooks
	 * @return	array 					Array with all available addressbooks
	 */
	public function get_carddav_addressbook($addressbook)
	{
		$servers = $this->get_carddav_server();

		foreach ($servers as $server)
		{
			if ($addressbook['id'] === $this->carddav_addressbook . $server['carddav_server_id'])
			{
				$addressbook['instance'] = new carddav_addressbook($server['carddav_server_id'], $server['label'], ($server['read_only'] == 1 ? true : false));
			}
		}

		return $addressbook;
	}

	/**
	 * Get CardDAV addressbook source
	 *
	 * @param	array	$addressbook	Array with all available addressbooks sources
	 * @return	array					Array with all available addressbooks sources
	 */
	public function get_carddav_addressbook_sources($addressbook)
	{
		$servers = $this->get_carddav_server();

		foreach ($servers as $server)
		{
			$carddav_addressbook = new carddav_addressbook($server['carddav_server_id'], $server['label'], ($server['read_only'] == 1 ? true : false));

			$addressbook['sources'][$this->carddav_addressbook . $server['carddav_server_id']] = array(
				'id' => $this->carddav_addressbook . $server['carddav_server_id'],
				'name' => $server['label'],
				'readonly' => $carddav_addressbook->readonly,
				'groups' => $carddav_addressbook->groups
			);
		}

		return $addressbook;
	}

	/**
	 * Check if CURL is installed or not
	 *
	 * @return	boolean
	 */
	private function check_curl_installed()
	{
		if (function_exists('curl_init'))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Synchronize CardDAV addressbook
	 *
	 * @param	boolean	$carddav_server_id	CardDAV server id to synchronize a single CardDAV server
	 * @param	boolean $ajax				Within a ajax request or not
	 * @return	void
	 */
	public function carddav_addressbook_sync($carddav_server_id = false, $ajax = true)
	{
		$servers	= $this->get_carddav_server();
		$result		= false;

		foreach ($servers as $server)
		{
			if ($carddav_server_id === false || $carddav_server_id == $server['carddav_server_id'])
			{
				$carddav_addressbook = new carddav_addressbook($server['carddav_server_id'], $server['label'], ($server['read_only'] == 1 ? true : false));
				$result = $carddav_addressbook->carddav_addressbook_sync($server);
			}
		}

		if ($ajax === true)
		{
			$rcmail = rcmail::get_instance();

			if ($result === true)
			{
				$rcmail->output->command('plugin.carddav_addressbook_message', array(
					'message' => $this->gettext('addressbook_synced'),
					'check' => true
				));
			}
			else
			{
				$rcmail->output->command('plugin.carddav_addressbook_message', array(
					'message' => $this->gettext('addressbook_sync_failed'),
					'check' => false
				));
			}
		}
	}

	/**
	 * Render CardDAV server settings
	 *
	 * @return	void
	 */
	public function carddav_server()
	{
		$rcmail = rcmail::get_instance();
		$this->register_handler('plugin.body', array($this, 'carddav_server_form'));
		$rcmail->output->set_pagetitle($this->gettext('settings'));
		$rcmail->output->send('plugin');
	}

	/**
	* Check if CardDAV server are available in the local database
	*
	* @return	boolean		If CardDAV-Server are available in the local database return true else false
	*/
	protected function carddav_server_available()
	{
		$rcmail		= rcmail::get_instance();
		$user_id	= $rcmail->user->data['user_id'];

		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_server')."
			WHERE
				user_id = ?
		";

		$result = $rcmail->db->query($query, $user_id);

		if ($rcmail->db->num_rows($result))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Check if it's possible to connect to the CardDAV server
	 *
	 * @return	boolean
	 */
	public function carddav_server_check_connection()
	{
		$url		= parse_input_value(base64_decode($_POST['_server_url']));
		$username	= parse_input_value(base64_decode($_POST['_username']));
		$password	= parse_input_value(base64_decode($_POST['_password']));

		$carddav_backend = new carddav_backend($url);
		$carddav_backend->set_auth($username, $password);

		return $carddav_backend->check_connection();
	}

	/**
	 * Render CardDAV server settings formular and register JavaScript actions
	 *
	 * @return	string	HTML CardDAV server formular
	 */
	public function carddav_server_form()
	{
		$rcmail		= rcmail::get_instance();
		$boxcontent	= null;

		if ($this->check_curl_installed())
		{
			$input_label = new html_inputfield(array(
				'name'		=> '_label',
				'id'		=> '_label',
				'size'		=> '17'
			));

			$input_server_url = new html_inputfield(array(
				'name'		=> '_server_url',
				'id'		=> '_server_url',
				'size'		=> '44'
			));

			$input_username = new html_inputfield(array(
				'name'		=> '_username',
				'id'		=> '_username',
				'size'		=> '17'
			));

			$input_password = new html_passwordfield(array(
				'name'		=> '_password',
				'id'		=> '_password',
				'size'		=> '17'
			));

			$input_read_only = new html_checkbox(array(
				'name'		=> '_read_only',
				'id'		=> '_read_only',
				'value'		=> 1
			));

			$input_submit = $rcmail->output->button(array(
				'command' => 'plugin.carddav-server-save',
				'type' => 'input',
				'class' => 'button mainaction',
				'label' => 'save'
			));

			$table = new html_table(array(
				'cols'	=> 6,
				'class'	=> 'carddav_server_list'
			));

			$table->add_header(array('width' => '13%'), $this->gettext('settings_label'));
			$table->add_header(array('width' => '35%'), $this->gettext('server'));
			$table->add_header(array('width' => '13%'), $this->gettext('username'));
			$table->add_header(array('width' => '13%'), $this->gettext('password'));
			$table->add_header(array('width' => '13%'), $this->gettext('settings_read_only'));
			$table->add_header(array('width' => '13%'), null);

			$table->add(array(), $input_label->show());
			$table->add(array(), $input_server_url->show());
			$table->add(array(), $input_username->show());
			$table->add(array(), $input_password->show());
			$table->add(array(), $input_read_only->show());
			$table->add(array(), $input_submit);

			$boxcontent .= html::div(array('class' => 'carddav_headline'), $this->gettext('settings_server_form'));
			$boxcontent .= html::div(array('class' => 'carddav_container'), $table->show());;
		}
		else
		{
			$rcmail->output->show_message($this->gettext('settings_curl_not_installed'), 'error');
		}

		$output = html::div(
			array('class' => 'box carddav'),
			html::div(array('class' => 'boxtitle'), $this->gettext('settings')).
				html::div(array('class' => 'boxcontent', 'id' => 'carddav_server_list'), $this->get_carddav_server_list()).
				html::div(array('class' => 'boxcontent'), $boxcontent).
				html::div(array('class' => 'boxcontent'), $this->get_carddav_url_list())
		);

		return $output;
	}

	/**
	 * Render a CardDAV server example URL list
	 *
	 * @return	string	$content	HTML CardDAV server example URL list
	 */
	public function get_carddav_url_list()
	{
		$content = null;

		$table = new html_table(array(
			'cols'	=> 2,
			'class'	=> 'carddav_server_list'
		));

		$table->add(array(), 'DAViCal');
		$table->add(array(), 'https://example.com/{resource|principal|username}/{collection}/');

		$table->add(array(), 'Apple Addressbook Server');
		$table->add(array(), 'https://example.com/addressbooks/users/{resource|principal|username}/{collection}/');

		$table->add(array(), 'memotoo');
		$table->add(array(), 'https://sync.memotoo.com/cardDAV/');

		$table->add(array(), 'SabreDAV');
		$table->add(array(), 'https://example.com/addressbooks/{resource|principal|username}/{collection}/');

		$table->add(array(), 'ownCloud');
		$table->add(array(), 'https://example.com/remote.php/carddav/addressbooks/{resource|principal|username}/{collection}/');

		$table->add(array(), 'SOGo');
		$table->add(array(), 'https://example.com/SOGo/dav/{resource|principal|username}/Contacts/{collection}/');

		$content .= html::div(array('class' => 'carddav_headline example_server_list'), $this->gettext('settings_example_server_list'));
		$content .= html::div(array('class' => 'carddav_container'), $table->show());

		return $content;
	}

	/**
	 * Save CardDAV server and execute first CardDAV contact sync
	 *
	 * @return void
	 */
	public function carddav_server_save()
	{
		$rcmail = rcmail::get_instance();

		if ($this->carddav_server_check_connection())
		{
			$user_id	= $rcmail->user->data['user_id'];
			$url		= parse_input_value(base64_decode($_POST['_server_url']));
			$username	= parse_input_value(base64_decode($_POST['_username']));
			$password	= parse_input_value(base64_decode($_POST['_password']));
			$label		= parse_input_value(base64_decode($_POST['_label']));
			$read_only	= (int) parse_input_value(base64_decode($_POST['_read_only']));

			$query = "
				INSERT INTO
					".get_table_name('carddav_server')." (user_id, url, username, password, label, read_only)
				VALUES
					(?, ?, ?, ?, ?, ?)
			";

			$rcmail->db->query($query, $user_id, $url, $username, $rcmail->encrypt($password), $label, $read_only);

			if ($rcmail->db->affected_rows())
			{
				$this->carddav_addressbook_sync($rcmail->db->insert_id(), false);

				$rcmail->output->command('plugin.carddav_server_message', array(
					'server_list' => $this->get_carddav_server_list(),
					'message' => $this->gettext('settings_saved'),
					'check' => true
				));
			}
			else
			{
				$rcmail->output->command('plugin.carddav_server_message', array(
					'message' => $this->gettext('settings_save_failed'),
					'check' => false
				));
			}
		}
		else
		{
			$rcmail->output->command('plugin.carddav_server_message', array(
				'message' => $this->gettext('settings_no_connection'),
				'check' => false
			));
		}
	}

	/**
	 * Delete CardDAV server and all related local contacts
	 *
	 * @return	void
	 */
	public function carddav_server_delete()
	{
		$rcmail = rcmail::get_instance();
		$user_id = $rcmail->user->data['user_id'];
		$carddav_server_id = parse_input_value(base64_decode($_POST['_carddav_server_id']));

		$query = "
			DELETE FROM
				".get_table_name('carddav_server')."
			WHERE
				user_id = ?
			AND
				carddav_server_id = ?
		";

		$rcmail->db->query($query, $user_id, $carddav_server_id);

		if ($rcmail->db->affected_rows())
		{
			$rcmail->output->command('plugin.carddav_server_message', array(
				'server_list' => $this->get_carddav_server_list(),
				'message' => $this->gettext('settings_deleted'),
				'check' => true
			));
		}
		else
		{
			$rcmail->output->command('plugin.carddav_server_message', array(
				'message' => $this->gettext('settings_delete_failed'),
				'check' => false
			));
		}
	}

	/**
	 * Extended write log with pre defined logfile name and add version before the message content
	 *
	 * @param	string	$message	Error log message
	 * @return	void
	 */
	public function write_log($message)
	{
		write_log('CardDAV', 'v' . self::VERSION . ' | ' . $message);
	}
}
