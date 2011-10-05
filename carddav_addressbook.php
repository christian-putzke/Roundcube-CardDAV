<?php

/**
 * Roundcube CardDAV addressbook extension
 * 
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Graviox Studios
 * @since 12.09.2011
 * @link http://www.graviox.de
 * @version 0.21
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
	 * Enter description here ...
	 * @var array $table_cols
	 */
	private $table_cols = array('name', 'email', 'firstname', 'surname');
	
	/**
	 * Enter description here ...
	 * @var array $fulltext_cols
	 */
	private $fulltext_cols = array('name', 'firstname', 'surname', 'middlename', 'nickname',
	      'jobtitle', 'organization', 'department', 'maidenname', 'email', 'phone',
	      'address', 'street', 'locality', 'zipcode', 'region', 'country', 'website', 'im', 'notes');
	
	/**
	 * Enter description here ...
	 * @var array $coltypes
	 */
	public $coltypes = array('name', 'firstname', 'surname', 'middlename', 'prefix', 'suffix', 'nickname',
	      'jobtitle', 'organization', 'department', 'assistant', 'manager',
	      'gender', 'maidenname', 'spouse', 'email', 'phone', 'address',
	      'birthday', 'anniversary', 'website', 'im', 'notes', 'photo');
	
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

	/**
	 * @see rcube_addressbook::get_name()
	 */
	public function get_name()
	{
		return $this->name;
	}
	
	/**
	 * get all CardDAV-Adressbook contacts from a specified CardDAV-Addressbook
	 *
	 * @param integer $carddav_server_id CardDAV-Server id
	 * @param boolean $all_data show all data (true) or not (false)
	 * @param array $limit query limits (start, length) 
	 * @return array $carddav_addressbook_contacts CardDAV-Adressbook contacts
	 */
	private function get_carddav_addressbook_contacts($carddav_server_id = null, $all_data = true, $limit = false)
	{
		$rcmail = rcmail::get_instance();
		$carddav_addressbook_contacts = array();
	
		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_contacts')."
			WHERE
				user_id = ?
			".($carddav_server_id !== null ? "AND carddav_server_id = ?" : null)."
			ORDER BY
				name ASC
		";
	
		if ($limit === false)
		{
			$result = $rcmail->db->query($query, $rcmail->user->data['user_id'], $carddav_server_id);
		}
		else
		{
			$result = $rcmail->db->limitquery($query, $limit['start'], $limit['length'], $rcmail->user->data['user_id'], $carddav_server_id);
		}
		
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
	
	/**
	* get group assignments of a specific CardDAV-Contact
	*
	* @param integer $carddav_contact_id CardDAV-Contact id
	* @return array $groups list of assigned groups
	*/
	public function get_record_groups($carddav_contact_id)
	{
		$groups = array();
	
		if ($this->groups === true)
		{
			$rcmail = rcmail::get_instance();
			
			$query = "
				SELECT
					carddav_server_id, label
				FROM
					".get_table_name('carddav_server')."
				WHERE
					user_id = ?
				AND
					carddav_server_id = (
						SELECT
							carddav_server_id
						FROM
							".get_table_name('carddav_contacts')."
						WHERE
							carddav_contact_id = ?
					)
			";
			
			$result = $rcmail->db->query($query, $rcmail->user->data['user_id'], $carddav_contact_id);
			
			if ($rcmail->db->num_rows())
			{
				while ($group = $rcmail->db->fetch_assoc($result))
				{
					$groups['CardDAV_'.$group['carddav_server_id']] = $group['label'];
				}
			}
		}
	
		return $groups;
	}
	
	/**
	* get count of CardDAV-Contacts specified CardDAV-Addressbook
	*
	* @param integer $carddav_server_id CardDAV-Server id
	* @return array $count count CardDAV-Contacts
	*/
	private function get_carddav_addressbook_contacts_count($carddav_server_id = null)
	{
		$rcmail = rcmail::get_instance();
	
		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_contacts')."
			WHERE
				user_id = ?
			".($carddav_server_id !== null ? "AND carddav_server_id = ?" : null)."
			ORDER BY
				name ASC
		";
	
		$result = $rcmail->db->query($query, $rcmail->user->data['user_id'], $carddav_server_id);
		$count = $rcmail->db->num_rows($result);
	
		return $count;
	}
	
	/**
	* get one CardDAV-Adressbook contact
	*
	* @param integer $carddav_contact_id CardDAV-Contact id
	* @return array $carddav_addressbook_contacts CardDAV-Adressbook contacts
	*/
	private function get_carddav_addressbook_contact($carddav_contact_id)
	{
		$rcmail = rcmail::get_instance();
	
		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_contacts')."
			WHERE
				user_id = ?
			AND
				carddav_contact_id = ?
		";
	
		$result = $rcmail->db->query($query, $rcmail->user->data['user_id'], $carddav_contact_id);
	
		if ($rcmail->db->num_rows($result))
		{
			return $rcmail->db->fetch_assoc($result);
		}
	
		return false;
	}

	/**
	 * @see rcube_addressbook::get_search_set()
	 */
	public function get_search_set()
	{
		return $this->filter;
	}

	/**
	 * @see rcube_addressbook::set_search_set()
	 */
	public function set_search_set($filter)
	{
		$this->filter = $filter;
	}

	/**
	 * @see rcube_addressbook::reset()
	 */
	public function reset()
	{
		$this->result = null;
		$this->filter = null;
	}

	/**
	 * synchronize CardDAV addressbook
	 * 
	 * @return boolean if no error occurred (true) else (false)
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
				
				if ($carddav_backend->check_connection())
				{
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
									
									$this->carddav_addressbook_update($server['carddav_server_id'], $carddav_content);
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
				else
				{
					return false;
				}
			}
		}
		
		return true;
	}
	
	/**
	 * add a vCard to the CardDAV-Addressbook
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
				".get_table_name('carddav_contacts')." (carddav_server_id, user_id, etag, vcard_id, vcard, name, email)
			VALUES
				(?, ?, ?, ?, ?, ?, ?)
		";
		
		$result = $rcmail->db->query(
			$query,
			$carddav_server_id,
			$rcmail->user->data['user_id'],
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
	 * update a vCard in the CardDAV-Addressbook
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
				vcard = ?,
				name = ?,
				email = ?
			WHERE
				vcard_id = ?
			AND
				carddav_server_id = ?
			AND
				user_id = ?
		";
		
		$result = $rcmail->db->query(
			$query,
			$carddav_content['etag'],
			$carddav_content['vcard'],
			$vcard->displayname,
			implode($vcard->email, ', '),
			$carddav_content['vcard_id'],
			$carddav_server_id,
			$rcmail->user->data['user_id']
		);
		
		if ($rcmail->db->affected_rows($result))
		{
			return true;
		}
		
		return false;
	}
	
	/**
	 * delete a vCard from the CardDAV-Addressbook
	 *
	 * @param integer $carddav_server_id CardDAV-Server id
	 * @param string $vcard_id vCard id
	 * @return boolean
	 */
	private function carddav_addressbook_delete($carddav_server_id, $vcard_id)
	{
		$rcmail = rcmail::get_instance();
		
		$query = "
			DELETE FROM
				".get_table_name('carddav_contacts')."
			WHERE
				vcard_id = ?
			AND
				carddav_server_id = ?
			AND
				user_id = ?
		";
		
		$result = $rcmail->db->query($query, $vcard_id, $carddav_server_id, $rcmail->user->data['user_id']);
		
		if ($rcmail->db->affected_rows($result))
		{
			return true;
		}
		
		return false;
	}
	
	/**
	 * list CardDAV-Adressbooks
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
				if ($search === null || preg_match('/'.strtolower($search).'/', strtolower($server['label'])))
				{
					$groups[] = array(
						'ID' => 'CardDAV_'.$server['carddav_server_id'],
						'name' => $server['label']
					);
				}
			}
		}
		
		return $groups;
	}

	/**
	 * return a list of CardDAV-Adressbook contacts
	 * 
	 * @see rcube_addressbook::list_records()
	 * @return rcube_result_set $this->result list of CardDAV-Adressbook contacts 
	 */
	public function list_records($cols = null, $subset = 0)
	{
		$this->result = $this->count();
		$carddav_server_id = (isset($this->group_id) ? str_replace('CardDAV_', null, $this->group_id) : null);
		$limit = array(
			'start' => ($subset < 0 ? $this->result->first + $this->page_size + $subset : $this->result->first),
			'length' => ($subset != 0 ? abs($subset) : $this->page_size)
		);
		
		$contacts = $this->get_carddav_addressbook_contacts($carddav_server_id, true, $limit);
		
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

	/**
	 * search and autocomplete contacts in the mail view
	 * 
	 * @param string $value searched string
	 * @return rcube_result_set $this->result list of CardDAV-Adressbook contacts
	 */
	private function search_carddav_addressbook_contacts($value)
	{
		$rcmail = rcmail::get_instance();
		$this->result = $this->count();
		
		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_contacts')."
			WHERE
				user_id = ?
			AND
				(".$rcmail->db->ilike('name', '%'.$value.'%')."
					OR
				".$rcmail->db->ilike('email', '%'.$value.'%').")
			ORDER BY
				name ASC
		";
		
		$result = $rcmail->db->query($query, $rcmail->user->data['user_id']);
		
		if ($rcmail->db->num_rows($result))
		{
			while ($contact = $rcmail->db->fetch_assoc($result))
			{
				$record['name'] = $contact['name'];
				$record['email'] = explode(', ', $contact['email']);
				
				$this->result->add($record);
			}
			
		}
		
		return $this->result;
	}
	
	/**
	 * @see rcube_addressbook::search()
	 */
	public function search($fields, $value, $strict = false, $select = true, $nocount = false, $required = array())
	{
		return $this->search_carddav_addressbook_contacts($value);
	}

	/**
	 * count CardDAV-Contacts for a specified CardDAV-Addressbook and return the result set
	 * 
	 * @return rcube_result_set
	 * @see rcube_addressbook::count()
	 */
	public function count()
	{
		$carddav_server_id = (isset($this->group_id) ? str_replace('CardDAV_', null, $this->group_id) : null);
		$count = $this->get_carddav_addressbook_contacts_count($carddav_server_id);
		
		return new rcube_result_set($count, ($this->list_page - 1) * $this->page_size);
	}

	/**
	 * @see rcube_addressbook::get_result()
	 */
	public function get_result()
	{
		return $this->result;
	}

	/**
	 * (non-PHPdoc)
	 * @see rcube_addressbook::get_record()
	 */
	public function get_record($carddav_contact_id, $assoc = false)
	{
		$contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
		$contact['ID'] = $contact[$this->primary_key];
		unset($contact['email']);
		
		$vcard = new rcube_vcard($contact['vcard']);
		$contact += $vcard->get_assoc();

		$this->result = new rcube_result_set(1);
		$this->result->add($contact);
		
		if ($assoc === true)
		{
			return $contact;
		}
		else
		{
			return $this->result;
		}
	}

	/**
	 * @see rcube_addressbook::create_group()
	 */
	function create_group($name)
	{
		return false;
	}

	/**
	 * @see rcube_addressbook::delete_group()
	 */
	function delete_group($gid)
	{
		return false;
	}

	/**
	 * @see rcube_addressbook::rename_group()
	 */
	function rename_group($gid, $newname)
	{
		return $newname;
	}

	/**
	 * @see rcube_addressbook::add_to_group()
	 */
	function add_to_group($group_id, $ids)
	{
		return false;
	}

	/**
	 * @see rcube_addressbook::remove_from_group()
	 */
	function remove_from_group($group_id, $ids)
	{
		 return false;
	}

	/**
	 * @see rcube_addressbook::set_group()
	 */
	function set_group($gid)
	{
		$this->group_id = $gid;
	}

}
