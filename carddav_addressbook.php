<?php

/**
 * Roundcube CardDAV addressbook extension
 * 
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Graviox Studios
 * @since 12.09.2011
 * @link http://www.graviox.de
 * @version 0.2
 * @license http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
 *
 */
class carddav_addressbook extends rcube_addressbook
{
	public $primary_key = 'carddav_contact_id';
	public $readonly = true;
	public $groups = true;
	public $group_id;

	private $filter;
	private $result;
	private $name;
	private $servers;
	private $cache;

	public function __construct($name = null, $servers = null)
	{
		$this->ready = true;
		$this->name = $name;
		$this->servers = $servers;
	}

	public function get_name()
	{
		return $this->name;
	}

	public function set_search_set($filter)
	{
		$this->filter = $filter;
	}

	public function get_search_set()
	{
		return $this->filter;
	}

	public function reset()
	{
		$this->result = null;
		$this->filter = null;
	}

	public function carddav_addressbook_sync() 
	{
		if (!empty($this->servers))
		{
			foreach ($this->servers as $server)
			{
				$carddav_backend = new carddav_backend($server['url']);
				$carddav_backend->set_auth($server['username'], $server['password']);
				$elements = $carddav_backend->get(false);
				
				$xml = new SimpleXMLElement($elements);
					
				if (!empty($xml->element))
				{
					foreach ($xml->element as $element)
					{
						// sync contacts
						// read only -> inserts / deletes from cardDAV-Server
					}
				}
			}
		}
	}
	
	public function list_groups($search = null)
	{
		$groups = array();
		
		if (!empty($this->servers))
		{
			foreach ($this->servers as $server)
			{
				$groups[] = array(
					'ID' => 'cardDAV_'.$server['carddav_server_id'],
					'name' => $server['label']
				);
			}
		}
		
		return $groups;
	}

	public function list_records($cols=null, $subset=0)
	{
		$this->result = $this->count();
		$this->result->add(array('ID' => '111', 'name' => "Example Contact", 'firstname' => "Example", 'surname' => "Contact", 'email' => "example@roundcube.net"));

		return $this->result;
	}

	public function search($fields, $value, $strict=false, $select=true, $nocount=false, $required=array())
	{
		// no search implemented, just list all records
		return $this->list_records();
	}

	public function count()
	{
		return new rcube_result_set(1, ($this->list_page - 1) * $this->page_size);
	}

	public function get_result()
	{
		return $this->result;
	}

	public function get_record($id, $assoc=false)
	{
		$this->list_records();
		$first = $this->result->first();
		$sql_arr = $first['ID'] == $id ? $first : null;

		return $assoc && $sql_arr ? $sql_arr : $this->result;
	}


	function create_group($name)
	{
		$result = false;

		return $result;
	}

	function delete_group($gid)
	{
		return false;
	}

	function rename_group($gid, $newname)
	{
		return $newname;
	}

	function add_to_group($group_id, $ids)
	{
		return false;
	}

	function remove_from_group($group_id, $ids)
	{
		 return false;
	}
	
	function set_group($gid)
	{
		$this->group_id = $gid;
		$this->cache = null;
	}

}
