<?php

/**
 * Roundcube CardDAV synchronization
 *
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Christian Putzke @ Graviox Studios
 * @since 31.03.2012
 * @link http://www.graviox.de/
 * @link https://twitter.com/graviox/
 * @version 0.5
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */
class carddav_synchronization_cronjob
{
	/**
	 * @var	string	$doc_root	Roundcubes document root
	 */
	private	$doc_root;

	/**
	 * @var	string	$rc_inset	Roundcubes iniset script
	 */
	private	$rc_iniset = 'program/include/iniset.php';

	/**
	 * Init CardDAV synchronization cronjob
	 *
	 * @param	boolean	$init	Initialization
	 */
	public function __construct($init = true)
	{
		if ($init === true)
		{
			$this->init();
		}
	}

	/**
	 * Init CardDAV synchronization cronjob
	 *
	 * @return	void
	 */
	public function init()
	{
		$this->detect_document_root();
		$this->include_rc_iniset();
	}

	/**
	 * Detect Roundcubes real document root
	 *
	 * @return	void
	 */
	private function detect_document_root()
	{
		$dir = dirname(__FILE__);
		$dir = str_replace('plugins\\carddav\\cronjob', null, $dir);
		$this->doc_root = str_replace('plugins/carddav/cronjob', null, $dir);
	}

	/**
	 * Include Roundcubes initset so that the internal Roundcube functions can be used inside this cronjob
	 *
	 * @return	void
	 */
	private  function include_rc_iniset()
	{
		if (file_exists($this->doc_root . $this->rc_iniset))
		{
			chdir($this->doc_root);
			require_once $this->doc_root . '/program/include/iniset.php';
		}
		else
		{
			die('Can\'t detect file path correctly! I got this as Roundcubes document root: '. $this->doc_root);
		}
	}

	/**
	 * Get all CardDAV servers
	 *
	 * @return	array	$servers	CardDAV server array with label, url, username, password (encrypted)
	 */
	private function get_carddav_servers()
	{
		$servers	= array();
		$rcmail		= rcmail::get_instance();

		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_server')."
		";

		$result = $rcmail->db->query($query);

		while($server = $rcmail->db->fetch_assoc($result))
		{
			$servers[] = $server;
		}

		return $servers;
	}

	/**
	 * Synchronize all available CardDAV servers
	 *
	 * @return	void
	 */
	public function synchronize()
	{
		$servers	= $this->get_carddav_servers();
		$rcmail		= rcmail::get_instance();

		carddav::write_log('CRONJOB: Starting automatic CardDAV synchronization!');

		if (!empty($servers))
		{
			foreach ($servers as $server)
			{
				$rcmail->user->data['user_id'] = $server['user_id'];
				$carddav_addressbook = new carddav_addressbook($server['carddav_server_id'], $server['label']);
				$carddav_addressbook->carddav_addressbook_sync($server);
			}
		}

		carddav::write_log('CRONJOB: Automatic CardDAV synchronization finished!');
	}
}

$cronjob = new carddav_synchronization_cronjob();
$cronjob->synchronize();