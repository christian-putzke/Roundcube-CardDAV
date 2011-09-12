<?php

/**
 * include required carddav classes
 */
require_once dirname(__FILE__).'/carddav_backend.php';
require_once dirname(__FILE__).'/carddav_addressbook.php';


/**
 * Roundcube cardDAV implementation
 * 
 * This is a cardDAV implementation for roundcube 0.6 or higher. It allows every user to add
 * multiple cardDAV server in their settings. The cardDAV contacts will be synchronized
 * automaticly with their addressbook.
 * 
 * 
 * @author Christian Putzke <cputzke@graviox.de>
 * @copyright Graviox Studios
 * @since 06.09.2011
 * @version 0.2
 * @license http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
 *
 */
class carddav extends rcube_plugin
{
	public $task = 'settings|addressbook';
	private $abook_id = 'cardDAV-Contacts';
	private $abook_name = null;
	
	
	public function init()
	{
		$rcmail = rcmail::get_instance();
		$this->add_texts('localization/', true);
		$this->abook_name = $this->gettext('addressbook_contacts');
		
		switch ($rcmail->task)
		{
			case 'settings':
				$this->register_action('plugin.carddav-server', array($this, 'carddav_server'));
				$this->register_action('plugin.carddav-server-check', array($this, 'carddav_server_check'));
				$this->register_action('plugin.carddav-server-save', array($this, 'carddav_server_save'));
				$this->register_action('plugin.carddav-server-delete', array($this, 'carddav_server_delete'));
				$this->include_script('carddav_settings.js');
			break;
			
			case 'addressbook':
				$this->add_hook('addressbooks_list', array($this, 'carddav_addressbook_sources'));
				$this->add_hook('addressbook_get', array($this, 'get_carddav_addressbook'));
			break;
		}
	}
	
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
	
	public function get_carddav_addressbook($addressbook)
	{
		if ($addressbook['id'] === $this->abook_id)
		{
			$addressbook['instance'] = new carddav_addressbook($this->abook_name, $this->get_carddav_server());
		}
	
		return $addressbook;
	}
	
	public function carddav_addressbook_sources($addressbook)
	{
		$abook = new carddav_addressbook($this->abook_name);
		
		$addressbook['sources'][$this->abook_id] = array (
			'id' => $this->abook_id,
			'name' => $this->abook_name,
			'readonly' => $abook->readonly,
			'groups' => $abook->groups,
		);
		
		return $addressbook;
	}
	
	public function carddav_server()
	{
		$this->register_handler('plugin.body', array($this, 'carddav_server_form'));
		
		$rcmail = rcmail::get_instance();
		$rcmail->output->set_pagetitle($this->gettext('settings'));
		$rcmail->output->send('plugin');
	}
	
	public function carddav_server_check()
	{
		$rcmail = rcmail::get_instance();
		$url = get_input_value('_server_url', RCUBE_INPUT_POST);
		$username = get_input_value('_username', RCUBE_INPUT_POST);
		$password = get_input_value('_password', RCUBE_INPUT_POST);
		
		$carddav_backend = new carddav_backend($url);
		$carddav_backend->set_auth($username, $password);
		
		if ($carddav_backend->check_connection() === true)
		{
			$rcmail->output->command('plugin.carddav_server_check', array('check' => 'true'));
		}
		else
		{
			$rcmail->output->command('plugin.carddav_server_check', array('check' => 'false'));
		}
	}
	
	public function carddav_server_form()
	{
		$rcmail = rcmail::get_instance();
		$servers = $this->get_carddav_server();
		
		$table = new html_table(array('cols' => 5));
		$table->add('title', $this->gettext('settings_label'));
		$table->add('title', $this->gettext('server'));
		$table->add('title', $this->gettext('username'));
		$table->add('title', $this->gettext('password'));
		$table->add(null, null);
		
		if (!empty($servers))
		{
			foreach ($servers as $server)
			{
				$table->add(null, $server['label']);
				$table->add(null, $server['url']);
				$table->add(null, $server['username']);
				$table->add(null, '**********');
				$table->add(null, html::a(array('href' => './?_task=settings&_action=plugin.carddav-server-delete&id='.$server['carddav_server_id']), 'delete'));
			}
		}
		
		$input_label = new html_inputfield(array(
			'name' => '_label',
			'id' => '_label', 
			'size' => '20'
		));
			
		$input_server_url = new html_inputfield(array(
			'name' => '_server_url',
			'id' => '_server_url', 
			'size' => '50'
		));
			
		$input_username = new html_inputfield(array(
			'name' => '_username',
			'id' => '_username', 
			'size' => '20'
		));
			
		$input_password = new html_passwordfield(array(
			'name' => '_password',
			'id' => '_password', 
			'size' => '20'
		));
			
		$input_submit = $rcmail->output->button(array(
			'command' => 'plugin.carddav-server-save',
			'type' => 'input',
			'class' => 'button mainaction',
			'label' => 'save'
		));
			
		$table->add(null, $input_label->show());
		$table->add(null, $input_server_url->show());
		$table->add(null, $input_username->show());
		$table->add(null, $input_password->show());
		$table->add(null, $input_submit);

		$rcmail->output->add_gui_object('carddavserverform', 'carddav-server-form');
		
		$out = html::div(
			array('class' => 'box'),
			html::div(array('class' => 'boxtitle'), $this->gettext('settings')).
			html::div(array('class' => 'boxcontent'), $table->show())
		);
		
		return $rcmail->output->form_tag(array(
			'id' => 'carddav-server-form',
			'name' => 'carddav-server-form',
			'method' => 'post',
			'action' => './?_task=settings&_action=plugin.carddav-server-save',
		), $out);
	}
	
	public function carddav_server_save()
	{
		$rcmail = rcmail::get_instance();
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
			$rcmail->output->show_message($this->gettext('settings_saved'), 'confirmation');
		}
		else
		{
			$rcmail->output->show_message($this->gettext('settings_save_failed'), 'error');
		}
		
		return $this->carddav_server();
	}
	
	public function carddav_server_delete()
	{
		$rcmail = rcmail::get_instance();
		$user_id = $rcmail->user->data['user_id'];
		$carddav_server_id = $_GET['id'];
		
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
			$rcmail->output->show_message($this->gettext('settings_deleted'), 'confirmation');
		}
		else
		{
			$rcmail->output->show_message($this->gettext('settings_delete_failed'), 'error');
		}
		
		return $this->carddav_server();
	}
}
