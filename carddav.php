<?php

// include required CardDAV classes
require_once dirname(__FILE__).'/carddav_backend.php';
require_once dirname(__FILE__).'/carddav_addressbook.php';

/**
 * Roundcube CardDAV implementation
 * 
 * This is a CardDAV implementation for roundcube 0.6 or higher. It allows every user to add
 * multiple CardDAV-Server in their settings. The CardDAV-Contacts will be synchronized
 * automaticly with their addressbook.
 * 
 * 
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Graviox Studios
 * @link http://www.graviox.de
 * @since 06.09.2011
 * @version 0.2
 * @license http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
 *
 */
class carddav extends rcube_plugin
{
	/**
	 * tasks where CardDAV-Plugin is loaded (pipe separated)
	 * 
	 * @var string
	 */
	public $task = 'settings|addressbook';
	
	/**
	 * CardDAV-Addressbook id
	 * 
	 * @var string
	 */
	protected $carddav_addressbook_id = 'carddav_contacts';
	
	/**
	 * CardDAV-Addressbook localized label
	 * 
	 * @var string
	 */
	protected $carddav_addressbook_label = null;
	
	/**
	 * init CardDAV-Plugins - register actions, include scripts, load texts, add hooks
	 * 
	 * @see rcube_plugin::init()
	 */
	public function init()
	{
		$rcmail = rcmail::get_instance();
		$this->add_texts('localization/', true);
		$this->carddav_addressbook_label = $this->gettext('addressbook_contacts');
		
		switch ($rcmail->task)
		{
			case 'settings':
				$this->register_action('plugin.carddav-server', array($this, 'carddav_server'));
				$this->register_action('plugin.carddav-server-save', array($this, 'carddav_server_save'));
				$this->register_action('plugin.carddav-server-delete', array($this, 'carddav_server_delete'));
				$this->include_script('carddav_settings.js');
			break;
			
			case 'addressbook':
				$skin_path = $this->local_skin_path();
				
				$this->add_hook('addressbooks_list', array($this, 'carddav_addressbook_sources'));
				$this->add_hook('addressbook_get', array($this, 'get_carddav_addressbook'));
				
				$this->register_action('plugin.carddav-addressbook-sync', array($this, 'carddav_addressbook_sync'));
				$this->include_script('carddav_addressbook.js');
				
				$this->add_button(array(
					'command' => 'plugin.carddav-addressbook-sync',
					'imagepas' => $skin_path.'/sync_pas.png',
					'imageact' => $skin_path.'/sync_act.png',
					'width' => 32,
					'height' => 32,
					'title' => 'carddav.addressbook_sync'
				), 'toolbar');
			break;
		}
	}
	
	/**
	 * get all CardDAV-Servers from the current user
	 * 
	 * @return array $servers CardDAV-Server array with label, url, username, password (encrypted)
	 */
	protected function get_carddav_server()
	{
		$servers = array();
		$rcmail = rcmail::get_instance();
		$user_id = $rcmail->user->data['user_id'];
	
		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_server')."
			WHERE
				user_id = ?
		";
	
		$result = $rcmail->db->query($query, $user_id);
	
		while($server = $rcmail->db->fetch_assoc($result))
		{
			$servers[] = $server;
		}
	
		return $servers;
	}
	
	/**
	* render available CardDAV-Server list in HTML
	*
	* @return string $output HTML rendered CardDAV-Server list
	*/
	protected function get_carddav_server_list()
	{
		$servers = $this->get_carddav_server();
	
		if (!empty($servers))
		{
			$rcmail = rcmail::get_instance();
			
			$table = new html_table(array(
				'cols' => 5,
				'width' => '950'
			));
			
			$table->add(array('class' => 'title', 'width' => '16%'), $this->gettext('settings_label'));
			$table->add(array('class' => 'title', 'width' => '36%'), $this->gettext('server'));
			$table->add(array('class' => 'title', 'width' => '16%'), $this->gettext('username'));
			$table->add(array('class' => 'title', 'width' => '16%'), $this->gettext('password'));
			$table->add(array('width' => '16%'), null);
			
			foreach ($servers as $server)
			{
				/* $rcmail->output->button() seems not to work in ajax requests
				$delete_submit = $rcmail->output->button(array(
					'command' => 'plugin.carddav-server-delete',
					'prop' => $server['carddav_server_id'],
					'type' => 'input',
					'class' => 'button mainaction',
					'label' => 'delete'
				));*/
				
				$delete_submit = '<input
					type="button"
					value="'.$this->gettext('delete').'"
					onclick="return rcmail.command(\'plugin.carddav-server-delete\', \''.$server['carddav_server_id'].'\', this)"
					class="button mainaction"
				/>';

				$table->add(null, $server['label']);
				$table->add(null, $server['url']);
				$table->add(null, $server['username']);
				$table->add(null, '**********');
				$table->add(null, $delete_submit);
			}
			
			return $table->show();
		}
	}
	
	/**
	 * get CardDAV-Addressbook instance
	 * 
	 * @param array $addressbook array with all available addressbooks
	 * @return array $addressbook array with all available addressbooks
	 */
	public function get_carddav_addressbook($addressbook)
	{
		if ($addressbook['id'] === $this->carddav_addressbook_id)
		{
			$addressbook['instance'] = new carddav_addressbook($this->carddav_addressbook_label, $this->get_carddav_server());
		}
	
		return $addressbook;
	}

	/**
	 * get CardDAV-Addressbook source
	 * 
	 * @param array $addressbook array with all available addressbooks sources
	 * @return array $addressbook array with all available addressbooks sources
	 */
	public function carddav_addressbook_sources($addressbook)
	{
		$carddav_addressbook = new carddav_addressbook($this->carddav_addressbook_label);
		
		$addressbook['sources'][$this->carddav_addressbook_id] = array(
			'id' => $this->carddav_addressbook_id,
			'name' => $this->carddav_addressbook_label,
			'readonly' => $carddav_addressbook->readonly,
			'groups' => $carddav_addressbook->groups
		);
		
		return $addressbook;
	}
	
	/**
	 * synchronize CardDAV addressbook
	 */
	protected function carddav_addressbook_sync()
	{
		// TODO: add addressbook contacts
		// $carddav_addressbook = new carddav_addressbook(null, $this->get_carddav_server());
		// $carddav_addressbook->carddav_addressbook_sync();
	}
	
	/**
	 * render CardDAV server settings
	 */
	public function carddav_server()
	{
		$rcmail = rcmail::get_instance();
		$this->register_handler('plugin.body', array($this, 'carddav_server_form'));
		$rcmail->output->set_pagetitle($this->gettext('settings'));
		$rcmail->output->send('plugin');
	}
	
	/**
	 * check if it's possible to connect to the CardDAV-Server
	 * 
	 * @see carddav_backend::check_connection()
	 * @return boolean $carddav_backend->check_connection()
	 */
	public function carddav_server_check_connection()
	{
		$rcmail = rcmail::get_instance();
		$url = get_input_value('_server_url', RCUBE_INPUT_POST);
		$username = get_input_value('_username', RCUBE_INPUT_POST);
		$password = get_input_value('_password', RCUBE_INPUT_POST);
		
		$carddav_backend = new carddav_backend($url);
		$carddav_backend->set_auth($username, $password);
		
		return $carddav_backend->check_connection();
	}

	/**
	 * render CardDAV-Server settings formular and register javascript actions
	 */
	public function carddav_server_form()
	{
		$rcmail = rcmail::get_instance();
		
		$input_label = new html_inputfield(array(
			'name' => '_label',
			'id' => '_label', 
			'size' => '17'
		));
			
		$input_server_url = new html_inputfield(array(
			'name' => '_server_url',
			'id' => '_server_url', 
			'size' => '44'
		));
			
		$input_username = new html_inputfield(array(
			'name' => '_username',
			'id' => '_username', 
			'size' => '17'
		));
			
		$input_password = new html_passwordfield(array(
			'name' => '_password',
			'id' => '_password', 
			'size' => '17'
		));
			
		$input_submit = $rcmail->output->button(array(
			'command' => 'plugin.carddav-server-save',
			'type' => 'input',
			'class' => 'button mainaction',
			'label' => 'save'
		));

		$table = new html_table(array(
			'cols' => 5,
			'width' => '950'
		));
		
		$table->add(array('class' => 'title', 'width' => '16%'), $this->gettext('settings_label'));
		$table->add(array('class' => 'title', 'width' => '36%'), $this->gettext('server'));
		$table->add(array('class' => 'title', 'width' => '16%'), $this->gettext('username'));
		$table->add(array('class' => 'title', 'width' => '16%'), $this->gettext('password'));
		$table->add(array('width' => '16%'), null);

		$table->add(null, $input_label->show());
		$table->add(null, $input_server_url->show());
		$table->add(null, $input_username->show());
		$table->add(null, $input_password->show());
		$table->add(null, $input_submit);

		$output = html::div(
			array('class' => 'box'),
			html::div(array('class' => 'boxtitle'), $this->gettext('settings')).
			html::div(array('class' => 'boxcontent', 'id' => 'carddav_server_list'), $this->get_carddav_server_list()).
			html::div(array('class' => 'boxcontent'), $table->show())
		);
		
		return $output;
	}
	
	/**
	 * save CardDAV-Server and execute first CardDAV-Contact sync
	 */
	public function carddav_server_save()
	{
		$rcmail = rcmail::get_instance();
		
		if ($this->carddav_server_check_connection())
		{
			$user_id = $rcmail->user->data['user_id'];
			$url = get_input_value('_server_url', RCUBE_INPUT_POST);
			$username = get_input_value('_username', RCUBE_INPUT_POST);
			$password = get_input_value('_password', RCUBE_INPUT_POST);
			$label = get_input_value('_label', RCUBE_INPUT_POST);
			
			$query = "
				INSERT INTO
					".get_table_name('carddav_server')." (user_id, url, username, password, label)
				VALUES
					(?, ?, ?, ?, ?)
			";
			
			$rcmail->db->query($query, $user_id, $url, $username, $rcmail->encrypt($password), $label);
			
			if ($rcmail->db->affected_rows())
			{
				$this->carddav_addressbook_sync();

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
	 * delete CardDAV-Server and all related local contacts
	 */
	public function carddav_server_delete()
	{
		$rcmail = rcmail::get_instance();
		$user_id = $rcmail->user->data['user_id'];
		$carddav_server_id = get_input_value('_carddav_server_id', RCUBE_INPUT_POST);
		
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
}
