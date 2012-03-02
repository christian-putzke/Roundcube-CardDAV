<?php

/**
 * Roundcube CardDAV addressbook extension
 *
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Graviox Studios
 * @since 12.09.2011
 * @link http://www.graviox.de
 * @version 0.4
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */
class carddav_addressbook extends rcube_addressbook
{
	/**
	 * database primary key
	 *
	 * @var string
	 */
	public $primary_key = 'carddav_contact_id';

	/**
	 * set addressbook readonly (true) or not (false)
	 *
	 * @var boolean
	 */
	public $readonly = false;

	/**
	 * allow addressbook groups (true) or not (false)
	 *
	 * @var boolean
	 */
	public $groups = true;

	/**
	 * internal addressbook group id
	 *
	 * @var string
	 */
	public $group_id;

	/**
	 * search filters
	 *
	 * @var array
	 */
	private $filter;

	/**
	* result set
	*
	* @var rcube_result_set
	*/
	private $result;

	/**
	* translated addressbook name
	*
	* @var string
	*/
	private $name;

	/**
	* CardDAV-Server id (just to limit carddav::get_carddav_server calls to one CardDAV-Server)
	*
	* @var mixed
	*/
	private $carddav_server_id = false;

	/**
	 * single and searchable database table columns
	 *
	 * @var array
	 */
	private $table_cols = array('name', 'firstname', 'surname', 'email');

	/**
	 * vCard "columns" used for the fulltext search (database column: words)
	 *
	 * @var array
	 */
	private $fulltext_cols = array('name', 'firstname', 'surname', 'middlename', 'nickname',
		  'jobtitle', 'organization', 'department', 'maidenname', 'email', 'phone',
		  'address', 'street', 'locality', 'zipcode', 'region', 'country', 'website', 'im', 'notes');

	/**
	 * vCard "column" types that will be displayed in the addressbook
	 *
	 * @var array
	 */
	public $coltypes = array('name', 'firstname', 'surname', 'middlename', 'prefix', 'suffix', 'nickname',
		  'jobtitle', 'organization', 'department', 'assistant', 'manager',
		  'gender', 'maidenname', 'spouse', 'email', 'phone', 'address',
		  'birthday', 'anniversary', 'website', 'im', 'notes', 'photo');

	/**
	 * id list separator
	 *
	 * @var string
	 */
	const SEPARATOR = ',';

	/**
	 * init CardDAV-Addressbook
	 *
	 * @param string translated addressbook name
	 * @param integer CardDAV-Server id
	 */
	public function __construct($name = null, $carddav_server_id = false)
	{
		$this->ready = true;
		$this->name = $name;
		$this->carddav_server_id = $carddav_server_id;
	}

	/**
	 * get translated addressbook name
	 *
	 * @return string $this->name translated addressbook name
	 */
	public function get_name()
	{
		return $this->name;
	}

	/**
	 * get all CardDAV-Adressbook contacts from a specified CardDAV-Addressbook
	 *
	 * @param integer CardDAV-Server id
	 * @param array limits (start, length)
	 * @return array CardDAV-Adressbook contacts
	 */
	private function get_carddav_addressbook_contacts($carddav_server_id = null, $limit = array())
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
				".$this->get_search_set()."
			ORDER BY
				name ASC
		";

		if (empty($limit))
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
				$carddav_addressbook_contacts[$contact['vcard_id']] = $contact;
			}
		}

		return $carddav_addressbook_contacts;
	}

	/**
	* get one CardDAV-Adressbook contact
	*
	* @param integer CardDAV-Contact id
	* @return array CardDAV-Adressbook contacts
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
	* get count of CardDAV-Contacts specified CardDAV-Addressbook
	*
	* @param integer CardDAV-Server id
	* @return integer count CardDAV-Contacts
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
				".$this->get_search_set()."
			ORDER BY
				name ASC
		";

		$result = $rcmail->db->query($query, $rcmail->user->data['user_id'], $carddav_server_id);

		return $rcmail->db->num_rows($result);
	}

	/**
	* get group assignments of a specific CardDAV-Contact
	*
	* @param integer CardDAV-Contact id
	* @return array list of assigned groups
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
	 * get result set
	 *
	* @return rcube_result_set result set
	*/
	public function get_result()
	{
		return $this->result;
	}

	/**
	 * @param integer CardDAV-Contact id
	 * @param boolean define if result should be assoc or rcube_result_set
	 *
	 * @return mixed returns contact or rcube_result_set
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
	* Getter for saved search properties
	*
	* @return mixed Search properties
	*/
	public function get_search_set()
	{
		return $this->filter;
	}

	/**
	 * Save a search string for future listings
	 *
	 * @param string SQL params to use in listing method
	 */
	public function set_search_set($filter)
	{
		$this->filter = $filter;
	}

	/**
	 * set database search filter
	 *
	 * @param mixed database field names
	 * @param string searched value
	 */
	public function set_filter($fields, $value)
	{
		$rcmail = rcmail::get_instance();

		if (is_array($fields))
		{
			$filter = "AND (";

			foreach ($fields as $field)
			{
				if (in_array($field, $this->table_cols))
				{
					$filter .= $rcmail->db->ilike($field, '%'.$value.'%')." OR ";
				}
			}

			$filter = substr($filter, 0, -4);
			$filter .= ")";
		}
		else
		{
			if (in_array($fields, $this->table_cols))
			{
				$filter = " AND ".$rcmail->db->ilike($fields, '%'.$value.'%');
			}
			else if ($fields == '*')
			{
				$filter = " AND ".$rcmail->db->ilike('words', '%'.$value.'%');
			}
		}
		$this->set_search_set($filter);
	}


	/**
	 * set internal addressbook group id
	 *
	* @param string internal addressbook group id
	*/
	public function set_group($group_id)
	{
		$this->group_id = $group_id;
	}

	/**
	 * reset cached filters and results
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
		$servers = carddav::get_carddav_server($this->carddav_server_id);

		if (!empty($servers))
		{
			foreach ($servers as $server)
			{
				$carddav_backend = new carddav_backend($server['url']);
				$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));

				if ($carddav_backend->check_connection())
				{
					$elements = $carddav_backend->get(false);
					$carddav_addressbook_contacts = $this->get_carddav_addressbook_contacts($server['carddav_server_id']);

					$xml = new SimpleXMLElement($elements);

					if (!empty($xml->element))
					{
						foreach ($xml->element as $element)
						{
							$element_id = (string) $element->id;
							$element_etag = (string) $element->etag;
							$element_last_modified = (string) $element->last_modified;

							if (isset($carddav_addressbook_contacts[$element_id]))
							{
								if ($carddav_addressbook_contacts[$element_id]['etag'] != $element_etag ||
									$carddav_addressbook_contacts[$element_id]['last_modified'] != $element_last_modified)
								{
									$carddav_content = array(
										'vcard' => $carddav_backend->get_vcard($element_id),
										'vcard_id' => $element_id,
										'etag' => $element_etag,
										'last_modified' => $element_last_modified
									);

									$this->carddav_addressbook_update($server['carddav_server_id'], $carddav_content);
								}
							}
							else
							{
								$carddav_content = array(
									'vcard' => $carddav_backend->get_vcard($element_id),
									'vcard_id' => $element_id,
									'etag' => $element_etag,
									'last_modified' => $element_last_modified
								);

								if (!empty($carddav_content['vcard']))
								{
									$this->carddav_addressbook_add($server['carddav_server_id'], $carddav_content);
								}
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
	 * synchronize CardDAV contact
	 *
	 * @param integer CardDAV contact id
	 * @param string vCard id
	 * @return boolean if no error occurred (true) else (false)
	 */
	public function carddav_contact_sync($carddav_contact_id, $vcard_id)
	{
		$rcmail = rcmail::get_instance();
		$server = current(carddav::get_carddav_server($this->carddav_server_id));

		if (!empty($server))
		{
			$carddav_backend = new carddav_backend($server['url']);
			$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));

			if ($carddav_backend->check_connection())
			{
				$xml_vcard = $carddav_backend->get_xml_vcard($vcard_id);
				$carddav_addressbook_contact = $this->get_carddav_addressbook_contact($carddav_contact_id);

				$xml = new SimpleXMLElement($xml_vcard);

				$element_id = (string) $xml->element->id;
				$element_etag = (string) $xml->element->etag;
				$element_last_modified = (string) $xml->element->last_modified;

				if ($carddav_addressbook_contact !== false && !empty($element_id))
				{
					if ($carddav_addressbook_contact['etag'] != $element_etag ||
						$carddav_addressbook_contact['last_modified'] != $element_last_modified)
					{
						$carddav_content = array(
							'vcard' => $carddav_backend->get_vcard($element_id),
							'vcard_id' => $element_id,
							'etag' => $element_etag,
							'last_modified' => $element_last_modified
						);

						$this->carddav_addressbook_update($server['carddav_server_id'], $carddav_content);
					}
				}
				else if (!empty($element_id))
				{
					$carddav_content = array(
						'vcard' => $carddav_backend->get_vcard($element_id),
						'vcard_id' => $element_id,
						'etag' => $element_etag,
						'last_modified' => $element_last_modified
					);

					if (!empty($carddav_content['vcard']))
					{
						$this->carddav_addressbook_add($server['carddav_server_id'], $carddav_content);
					}
				}
				else
				{
					$this->carddav_addressbook_delete($server['carddav_server_id'], $vcard_id);
				}
			}
		}
		else
		{
			return false;
		}

		return true;
	}

	/**
	 * add a vCard to the CardDAV-Addressbook
	 *
	 * @param integer CardDAV-Server id
	 * @param array CardDAV contents like vCard, vCard id, etag
	 * @return boolean
	 */
	private function carddav_addressbook_add($carddav_server_id, $carddav_content)
	{
		$rcmail = rcmail::get_instance();
		$vcard = new rcube_vcard($carddav_content['vcard']);

		$query = "
			INSERT INTO
				".get_table_name('carddav_contacts')." (carddav_server_id, user_id, etag, last_modified, vcard_id, vcard, words, firstname, surname, name, email)
			VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		";

		$database_column_contents = $this->get_database_column_contents($vcard->get_assoc());

		$result = $rcmail->db->query(
			$query,
			$carddav_server_id,
			$rcmail->user->data['user_id'],
			$carddav_content['etag'],
			$carddav_content['last_modified'],
			$carddav_content['vcard_id'],
			$carddav_content['vcard'],
			$database_column_contents['words'],
			$database_column_contents['firstname'],
			$database_column_contents['surname'],
			$database_column_contents['name'],
			$database_column_contents['email']
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
	 * @param integer CardDAV-Server id
	 * @param array CardDAV contents like vCard, vCard id, etag
	 * @return boolean
	 */
	private function carddav_addressbook_update($carddav_server_id, $carddav_content)
	{
		$rcmail = rcmail::get_instance();
		$vcard = new rcube_vcard($carddav_content['vcard']);

		$database_column_contents = $this->get_database_column_contents($vcard->get_assoc());

		$query = "
			UPDATE
				".get_table_name('carddav_contacts')."
			SET
				etag = ?,
				last_modified = ?,
				vcard = ?,
				words = ?,
				firstname = ?,
				surname = ?,
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
			$carddav_content['last_modified'],
			$carddav_content['vcard'],
			$database_column_contents['words'],
			$database_column_contents['firstname'],
			$database_column_contents['surname'],
			$database_column_contents['name'],
			$database_column_contents['email'],
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
	 * @param integer CardDAV-Server id
	 * @param string vCard id
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

	private function carddav_add()
	{
	}

	/**
	 * updates the CardDAV-Server contact
	 *
	 * @param $carddav_contact_id integer CardDAV-Contact id
	 * @param $vcard New vCard
	 */
	private function carddav_update($carddav_contact_id, $vcard)
	{
		$rcmail = rcmail::get_instance();
		$contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
		$server = current(carddav::get_carddav_server($contact['carddav_server_id']));

		$carddav_backend = new carddav_backend($server['url']);
		$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));

		if ($carddav_backend->check_connection())
		{
			$carddav_backend->update($vcard, $contact['vcard_id']);
			$carddav_addressbook = new carddav_addressbook(null, $contact['carddav_server_id']);
			$carddav_addressbook->carddav_contact_sync($carddav_contact_id, $contact['vcard_id']);

			return true;
		}

		return false;
	}

	/**
	 * deletes the CardDAV-Server contact
	 *
	 * @param $carddav_contact_id array CardDAV-Contact ids
	 */
	private function carddav_delete($carddav_contact_ids)
	{
		$rcmail = rcmail::get_instance();
		$last_server_id = 0;

		foreach ($carddav_contact_ids as $carddav_contact_id)
		{
			$contact = $this->get_carddav_addressbook_contact($carddav_contact_id);

			if ($last_server_id != $contact['carddav_server_id'])
			{
				$server = current(carddav::get_carddav_server($contact['carddav_server_id']));
				$carddav_backend = new carddav_backend($server['url']);
				$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));
				$carddav_addressbook = new carddav_addressbook(null, $contact['carddav_server_id']);
			}

			if (!$carddav_backend->check_connection())
			{
				return false;
			}

			$carddav_backend->delete($contact['vcard_id']);
			$carddav_addressbook->carddav_contact_sync($carddav_contact_id, $contact['vcard_id']);
			$last_server_id = $contact['carddav_server_id'];
		}

		return count($carddav_contact_ids);
	}

	/**
	 * list CardDAV-Adressbooks
	 *
	 * @param string Optional search string to match group name
	 * @return array list of CardDAV-Adressbooks
	 */
	public function list_groups($search = null)
	{
		$groups = array();
		$servers = carddav::get_carddav_server();

		if (!empty($servers))
		{
			foreach ($servers as $server)
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
	 * @return rcube_result_set $this->result list of CardDAV-Adressbook contacts
	 */
	public function list_records($columns = null, $subset = 0)
	{
		$this->result = $this->count();
		$carddav_server_id = (isset($this->group_id) ? str_replace('CardDAV_', null, $this->group_id) : null);
		$limit = array(
			'start' => ($subset < 0 ? $this->result->first + $this->page_size + $subset : $this->result->first),
			'length' => ($subset != 0 ? abs($subset) : $this->page_size)
		);

		$contacts = $this->get_carddav_addressbook_contacts($carddav_server_id, $limit);

		if (!empty($contacts))
		{
			foreach ($contacts as $carddav_contact_id => $contact)
			{
				$record = array();
				$record['ID'] = $contact[$this->primary_key];

				if ($columns === null)
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
	 * @param string searched string
	 * @return rcube_result_set $this->result list of searched CardDAV-Adressbook contacts
	 */
	private function search_carddav_addressbook_contacts()
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
			".$this->get_search_set()."
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
	 * search method (autocomplete, addressbook)
	 *
	 * @return rcube_result_set list of searched CardDAV-Adressbook contacts
	 */
	public function search($fields, $value, $strict = false, $select = true, $nocount = false, $required = array())
	{
		$this->set_filter($fields, $value);
		return $this->search_carddav_addressbook_contacts();
	}

	/**
	 * count CardDAV-Contacts for a specified CardDAV-Addressbook and return the result set
	 *
	 * @return rcube_result_set
	 */
	public function count()
	{
		$carddav_server_id = (isset($this->group_id) ? str_replace('CardDAV_', null, $this->group_id) : null);
		$count = $this->get_carddav_addressbook_contacts_count($carddav_server_id);

		return new rcube_result_set($count, ($this->list_page - 1) * $this->page_size);
	}

	/**
	 * @see rcube_addressbook::create_group()
	 */
	public function create_group($name)
	{
		return false;
	}

	/**
	 * @see rcube_addressbook::delete_group()
	 */
	public function delete_group($gid)
	{
		return false;
	}

	/**
	 * @see rcube_addressbook::rename_group()
	 */
	public function rename_group($gid, $newname)
	{
		return false;
	}

	/**
	 * @see rcube_addressbook::add_to_group()
	 */
	public function add_to_group($group_id, $ids)
	{
		return false;
	}

	/**
	 * @see rcube_addressbook::remove_from_group()
	 */
	public function remove_from_group($group_id, $ids)
	{
		 return false;
	}

    /**
     * Create a new contact record
     *
     * @param array Associative array with save data
     * @return integer|boolean The created record ID on success, False on error
     */
    function insert($save_data, $check=false)
    {
//    	$rcmail = rcmail::get_instance();
//    	$carddav_server_id = (isset($this->group_id) ? str_replace('CardDAV_', null, $this->group_id) : null);
//
//    	if ($carddav_server_id === null)
//    	{
//    		$rcmail->output->show_message('please select at first the addressbook CardDAV-Group where you want to add the new contact', 'error');
//    		return false;
//    	}
//
//        if (!is_array($save_data))
//            return false;
//
//        $insert_id = $existing = false;
//
//        if ($check) {
//            foreach ($save_data as $col => $values) {
//                if (strpos($col, 'email') === 0) {
//                    foreach ((array)$values as $email) {
//                        if ($existing = $this->search('email', $email, false, false))
//                            break 2;
//                    }
//                }
//            }
//        }
//
//        $save_data = $this->convert_save_data($save_data);
//        $a_insert_cols = $a_insert_values = array();
//
//        foreach ($save_data as $col => $value) {
//            $a_insert_cols[]   = $this->db->quoteIdentifier($col);
//            $a_insert_values[] = $this->db->quote($value);
//        }
//
//        if (!$existing->count && !empty($a_insert_cols)) {
//            $this->db->query(
//                "INSERT INTO ".get_table_name($this->db_name).
//                " (user_id, changed, del, ".join(', ', $a_insert_cols).")".
//                " VALUES (".intval($this->user_id).", ".$this->db->now().", 0, ".join(', ', $a_insert_values).")"
//            );
//
//            $insert_id = $this->db->insert_id($this->db_name);
//        }
//
//        // also add the newly created contact to the active group
//        if ($insert_id && $this->group_id)
//            $this->add_to_group($this->group_id, $insert_id);
//
//        $this->cache = null;
//
//        return $insert_id;
    }

	/**
	 *
	 * @param int CardDAV-Contact id
	 * @param array vCard parameters
	 */
	public function update($carddav_contact_id, $save_data)
	{
		$record = $this->get_record($carddav_contact_id, true);
		$database_column_contents = $this->get_database_column_contents($save_data, $record);

		return $this->carddav_update($carddav_contact_id, $database_column_contents['vcard']);
	}

	/**
	 * Delete one or more contact records
	 *
	 * @param array   Record identifiers
	 */
	function delete($carddav_contact_ids, $force = true)
	{
		if (!is_array($carddav_contact_ids))
		{
			$carddav_contact_ids = explode(self::SEPARATOR, $carddav_contact_ids);
		}
		return $this->carddav_delete($carddav_contact_ids);
	}

	/**
	 * convert vCard changes and return database relevant fileds including contents
	 *
	 * @param array new vCard values
	 * @param array original vCard
	 *
	 * @return array $database_column_contents database column contents
	 */
	private function get_database_column_contents($save_data, $record = array())
	{
		$words = '';
		$database_column_contents = array();

		$vcard = new rcube_vcard($record['vcard'] ? $record['vcard'] : $save_data['vcard']);
		$vcard->reset();

		foreach ($save_data as $key => $values)
		{
			list($field, $section) = explode(':', $key);
			$fulltext = in_array($field, $this->fulltext_cols);

			foreach ((array)$values as $value)
			{
				if (isset($value))
				{
					$vcard->set($field, $value, $section);
				}

				if ($fulltext && is_array($value))
				{
					$words .= ' ' . self::normalize_string(join(" ", $value));
				}
				else if ($fulltext && strlen($value) >= 3)
				{
					$words .= ' ' . self::normalize_string($value);
				}
			}
		}

		$database_column_contents['vcard'] = $vcard->export(false);

		foreach ($this->table_cols as $column)
		{
			$key = $column;

			if (!isset($save_data[$key]))
			{
				$key .= ':home';
			}
			if (isset($save_data[$key]))
			{
				$database_column_contents[$column] = is_array($save_data[$key]) ? implode(',', $save_data[$key]) : $save_data[$key];
			}
		}

		$database_column_contents['email'] = implode(', ', $vcard->email);
		$database_column_contents['words'] = trim(implode(' ', array_unique(explode(' ', $words))));

		return $database_column_contents;
	}
}
