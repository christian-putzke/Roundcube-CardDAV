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
	/**
	 * database primary key
	 * 
	 * $primary_key string
	 */
	public $primary_key = 'carddav_contact_id';
	
	/**
	 * set addressbook readonly (true) or not (false)
	 * 
	 * $readonly boolean
	 */
	public $readonly = true;
	
	/**
	 * allow addressbook groups (true) or not (false)
	 * 
	 * $groups boolean
	 */
	public $groups = true;
	
	/**
	 * internal addressbook group id
	 * 
	 * @group_id string
	 */
	public $group_id;

	/**
	 * Enter description here ...
	 * 
	 * $filter unknown_type
	 */
	private $filter;
	
	/**
	* Enter description here ...
	*
	* $result unknown_type
	*/
	private $result;
	
	/**
	* translated addressbook name
	*
	* $name strings
	*/
	private $name;
	
	/**
	* all CardDAV-Servers from the logged in user
	*
	* $servers unknown_type
	*/
	private $servers;

	/**
	 * init CardDAV-Addressbook
	 * 
	 * @param strings $name translated addressbook name
	 * @param array $servers all CardDAV-Servers from the logged in user
	 */
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
	
	/**
	 * get all MySQL CardDAV-Adressbook contacts from a specified MySQL CardDAV-Addressbook
	 *
	 * @param integer $carddav_server_id
	 * @return array $carddav_addressbook_contacts CardDAV-Adressbook contacts
	 */
	private function get_carddav_addressbook_contacts($carddav_server_id, $all_data = true)
	{
		$rcmail = rcmail::get_instance();
		$carddav_addressbook_contacts = array();
	
		$query = "
						SELECT
							*
						FROM
							".get_table_name('carddav_contacts')."
						WHERE
							carddav_server_id = ?
					";
	
		$result = $rcmail->db->query($query, $carddav_server_id);
	
		if ($rcmail->db->num_rows($result))
		{
			while ($contact = $rcmail->db->fetch_assoc($result))
			{
				if ($all_data === false)
				{
					$carddav_addressbook_contacts[$contact['vcard_id']] = $contact['etag'];
				}
				else
				{
					$carddav_addressbook_contacts[$contact['vcard_id']] = $contact;
				}
			}
		}
	
		return $carddav_addressbook_contacts;
	}
	
	public function get_search_set()
	{
		return $this->filter;
	}
	
	public function set_search_set($filter)
	{
		$this->filter = $filter;
	}

	public function reset()
	{
		$this->result = null;
		$this->filter = null;
	}

	/**
	 * synchronize CardDAV addressbook
	 */
	public function carddav_addressbook_sync() 
	{
		$rcmail = rcmail::get_instance();
		
		if (!empty($this->servers))
		{
			foreach ($this->servers as $server)
			{
				$carddav_backend = new carddav_backend($server['url']);
				$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));
				$elements = $carddav_backend->get(false);
				$carddav_addressbook_contacts = $this->get_carddav_addressbook_contacts($server['carddav_server_id'], false);
				
				$xml = new SimpleXMLElement($elements);
				
				if (!empty($xml->element))
				{
					foreach ($xml->element as $element)
					{
						$element_id = (string) $element->id;
						$element_etag = (string) $element->etag;
						
						if (isset($carddav_addressbook_contacts[$element_id]))
						{
							if ($carddav_addressbook_contacts[$element_id] != $element_etag)
							{
								$carddav_content = array(
									'vcard' => $carddav_backend->get_vcard($element_id),
									'vcard_id' => $element_id,
									'etag' => $element_etag 
								);
								
								$this->carddav_addressbook_update($server['carddav_server_id'], $carddav_backend->get_vcard($element_id));
							}
						}
						else
						{
							$carddav_content = array(
								'vcard' => $carddav_backend->get_vcard($element_id),
								'vcard_id' => $element_id,
								'etag' => $element_etag 
							);
							
							$this->carddav_addressbook_add($server['carddav_server_id'], $carddav_content);
						}
						unset($carddav_addressbook_contacts[$element_id]);
					}
					
					if (!empty($carddav_addressbook_contacts))
					{
						foreach ($carddav_addressbook_contacts as $vcard_id => $etag)
						{
							$this->carddav_addressbook_delete($server['carddav_server_id'], $vcard_id);
						}
					}
				}
			}
		}
	}
	
	/**
	 * add a vCard to the MySQL CardDAV-Addressbook
	 * 
	 * @param integer $carddav_server_id CardDAV-Server id
	 * @param array $carddav_content CardDAV contents like vCard, vCard id, etag
	 * @return boolean
	 */
	private function carddav_addressbook_add($carddav_server_id, $carddav_content)
	{
		$rcmail = rcmail::get_instance();
		$vcard = new rcube_vcard($carddav_content['vcard']);

		$query = "
			INSERT INTO
				".get_table_name('carddav_contacts')." (carddav_server_id, etag, vcard_id, vcard, name, email)
			VALUES
				(?, ?, ?, ?, ?, ?)
		";
		
		$result = $rcmail->db->query(
			$query,
			$carddav_server_id,
			$carddav_content['etag'],
			$carddav_content['vcard_id'],
			$carddav_content['vcard'],
			$vcard->displayname,
			implode($vcard->email, ', ')
		);
		
		if ($rcmail->db->affected_rows($result))
		{
			return true;
		}

		return false;
	}

	/**
	 * update a vCard in the MySQL CardDAV-Addressbook
	 *
	 * @param integer $carddav_server_id CardDAV-Server id
	 * @param array $carddav_content CardDAV contents like vCard, vCard id, etag
	 * @return boolean
	 */
	private function carddav_addressbook_update($carddav_server_id, $carddav_content)
	{
		$rcmail = rcmail::get_instance();
		$vcard = new rcube_vcard($carddav_content['vcard']);
		
		$query = "
			UPDATE
				".get_table_name('carddav_contacts')."
			SET
				etag = ?,
				vcard ? ,
				name = ?,
				email = ?
			WEHRE
				vcard_id = ?
			AND
				carddav_server_id = ?
		";
		
		$result = $rcmail->db->query(
			$query,
			$carddav_content['etag'],
			$carddav_content['vcard'],
			$vcard->displayname,
			implode($vcard->email, ', '),
			$carddav_content['vcard_id'],
			$carddav_server_id
		);
		
		if ($rcmail->db->affected_rows($result))
		{
			return true;
		}
		
		return false;
	}
	
	/**
	 * delete a vCard from the MySQL CardDAV-Addressbook
	 *
	 * @param integer $carddav_server_id CardDAV-Server id
	 * @param array $carddav_content vCard id
	 * @return boolean
	 */
	private function carddav_addressbook_delete($carddav_server_id, $vcard_id)
	{
		$rcmail = rcmail::get_instance();
		
		$query = "
			DELETE FROM
				".get_table_name('carddav_contacts')."
			WEHRE
				vcard_id = ?
			AND
				carddav_server_id = ?
		";
		
		$result = $rcmail->db->query($query, $vcard_id, $carddav_server_id);
		
		if ($rcmail->db->affected_rows($result))
		{
			return true;
		}
		
		return false;
	}
	
	/**
	 * list MySQL CardDAV-Adressbooks
	 * 
	 * @param string $search Optional search string to match group name
	 * @see rcube_addressbook::list_groups()
	 * @return array $groups list of CardDAV-Adressbooks
	 */
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

	/**
	 * return a list of MySQL CardDAV-Adressbook contacts
	 * 
	 * @see rcube_addressbook::list_records()
	 * @return rcube_result_set $this->result list of CardDAV-Adressbook contacts 
	 */
	public function list_records($cols = null, $subset = 0)
	{
		$this->result = $this->count();
		$carddav_server_id = str_replace('cardDAV_', null, $this->group_id);
		$contacts = $this->get_carddav_addressbook_contacts($carddav_server_id);
		
		if (!empty($contacts))
		{
			foreach ($contacts as $carddav_contact_id => $contact)
			{
				$record = array();
				$record['ID'] = $contact[$this->primary_key];
				
				if ($cols === null)
				{
					$vcard = new rcube_vcard($contact['vcard']);
					$record += $vcard->get_assoc();
				}
				else
				{
					$record['name'] = $contact['name'];
					$record['email'] = explode(', ', $contact['email']);
				}
				
				$this->result->add($record);
			}
		}

		return $this->result;
	}

	public function search($fields, $value, $strict = false, $select = true, $nocount = false, $required = array())
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
		return false;
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
	}

}
