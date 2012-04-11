<?php

/**
 * Roundcube CardDAV addressbook extension
 *
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Christian Putzke @ Graviox Studios
 * @since 12.09.2011
 * @link http://www.graviox.de/
 * @link https://twitter.com/graviox/
 * @version 0.5.1
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */
class carddav_addressbook extends rcube_addressbook
{
	/**
	 * Database primary key
	 *
	 * @var	string
	 */
	public $primary_key = 'carddav_contact_id';

	/**
	 * Sets addressbook readonly (true) or not (false)
	 *
	 * @var	boolean
	 */
	public $readonly = false;

	/**
	 * Allow addressbook groups (true) or not (false)
	 *
	 * @var	boolean
	 */
	public $groups = false;

	/**
	 * Internal addressbook group id
	 *
	 * @var string
	 */
	public $group_id;

	/**
	 * Search filters
	 *
	 * @var	array
	 */
	private $filter;

	/**
	* Result set
	*
	* @var	rcube_result_set
	*/
	private $result;

	/**
	* Translated addressbook name
	*
	* @var	string
	*/
	private $name;

	/**
	* CardDAV server id
	*
	* @var	integer
	*/
	private $carddav_server_id = false;

	/**
	 * Single and searchable database table columns
	 *
	 * @var	array
	 */
	private $table_cols = array('name', 'firstname', 'surname', 'email');

	/**
	 * vCard fields used for the fulltext search (database column: words)
	 *
	 * @var	array
	 */
	private $fulltext_cols = array('name', 'firstname', 'surname', 'middlename', 'nickname',
		  'jobtitle', 'organization', 'department', 'maidenname', 'email', 'phone',
		  'address', 'street', 'locality', 'zipcode', 'region', 'country', 'website', 'im', 'notes');

	/**
	 * vCard fields that will be displayed in the addressbook
	 *
	 * @var	array
	 */
	public $coltypes = array('name', 'firstname', 'surname', 'middlename', 'prefix', 'suffix', 'nickname',
		  'jobtitle', 'organization', 'department', 'assistant', 'manager',
		  'gender', 'maidenname', 'spouse', 'email', 'phone', 'address',
		  'birthday', 'anniversary', 'website', 'im', 'notes', 'photo');

	/**
	 * Id list separator
	 *
	 * @constant	string
	 */
	const SEPARATOR = ',';

	/**
	 * Init CardDAV addressbook
	 *
	 * @param	string		$carddav_server_id		Translated addressbook name
	 * @param	integer		$name					CardDAV server id
	 * @return	void
	 */
	public function __construct($carddav_server_id, $name, $readonly)
	{
		$this->ready				= true;
		$this->name					= $name;
		$this->carddav_server_id	= $carddav_server_id;
		$this->readonly				= $readonly;
	}

	/**
	 * Get translated addressbook name
	 *
	 * @return	string	$this->name	Translated addressbook name
	 */
	public function get_name()
	{
		return $this->name;
	}

	/**
	 * Get all CardDAV adressbook contacts
	 *
	 * @param	array $limit	Limits (limit, offset)
	 * @return	array 			CardDAV adressbook contacts
	 */
	private function get_carddav_addressbook_contacts($limit = array())
	{
		$rcmail							= rcmail::get_instance();
		$carddav_addressbook_contacts	= array();

		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_contacts')."
			WHERE
				user_id = ?
			AND
				carddav_server_id = ?
				".$this->get_search_set()."
			ORDER BY
				name ASC
		";

		if (empty($limit))
		{
			$result = $rcmail->db->query($query, $rcmail->user->data['user_id'], $this->carddav_server_id);
		}
		else
		{
			$result = $rcmail->db->limitquery($query, $limit['start'], $limit['length'], $rcmail->user->data['user_id'], $this->carddav_server_id);
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
	* Get one CardDAV adressbook contact
	*
	* @param	integer	$carddav_contact_id		CardDAV contact id
	* @return	array 							CardDAV adressbook contact
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
	* Get count of CardDAV contacts specified CardDAV addressbook
	*
	* @return	integer		Count of the CardDAV contacts
	*/
	private function get_carddav_addressbook_contacts_count()
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
				carddav_server_id = ?
				".$this->get_search_set()."
			ORDER BY
				name ASC
		";

		$result = $rcmail->db->query($query, $rcmail->user->data['user_id'], $this->carddav_server_id);

		return $rcmail->db->num_rows($result);
	}

	/**
	 * Get result set
	 *
	 * @return	$this->result	rcube_result_set	Roundcube result set
	 */
	public function get_result()
	{
		return $this->result;
	}

	/**
	 *
	 *
	 * @param	integer		$carddav_contact_id		CardDAV contact id
	 * @param	boolean		$assoc					Define if result should be an assoc array or rcube_result_set
	 * @return	mixed								Returns contact as an assoc array or rcube_result_set
	 */
	public function get_record($carddav_contact_id, $assoc = false)
	{
		$contact		= $this->get_carddav_addressbook_contact($carddav_contact_id);
		$contact['ID']	= $contact[$this->primary_key];

		unset($contact['email']);

		$vcard		= new rcube_vcard($contact['vcard']);
		$contact	+= $vcard->get_assoc();

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
	* @return	$this->filter	array	Search properties
	*/
	public function get_search_set()
	{
		return $this->filter;
	}

	/**
	 * Save a search string for future listings
	 *
	 * @param	string	$filter		SQL params to use in listing method
	 * @return	void
	 */
	public function set_search_set($filter)
	{
		$this->filter = $filter;
	}

	/**
	 * Set database search filter
	 *
	 * @param	mixed	$fields		Database field names
	 * @param	string	$value		Searched value
	 * @return	void
	 */
	public function set_filter($fields, $value)
	{
		$rcmail		= rcmail::get_instance();
		$filter		= null;

		if (is_array($fields))
		{
			$filter = "AND (";

			foreach ($fields as $field)
			{
				if (in_array($field, $this->table_cols) || $fields == $this->primary_key)
				{
					$filter .= $rcmail->db->ilike($field, '%'.$value.'%')." OR ";
				}
			}

			$filter = substr($filter, 0, -4);
			$filter .= ")";
		}
		else
		{
			if (in_array($fields, $this->table_cols) || $fields == $this->primary_key)
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
	 * Sets internal addressbook group id
	 *
	* @param	string	$group_id	Internal addressbook group id
	* @return	void
	*/
	public function set_group($group_id)
	{
		$this->group_id = $group_id;
	}

	/**
	 * Reset cached filters and results
	 *
	 * @return	void
	 */
	public function reset()
	{
		$this->result = null;
		$this->filter = null;
	}

	/**
	 * Synchronize CardDAV-Addressbook
	 *
	 * @param	array		$server					CardDAV server array
	 * @param	integer		$carddav_contact_id		CardDAV contact id
	 * @param	string		$vcard_id				vCard id
	 * @return	boolean								if no error occurred "true" else "false"
	 */
	public function carddav_addressbook_sync($server, $carddav_contact_id = null, $vcard_id = null)
	{
		$rcmail = rcmail::get_instance();
		$any_data_synced = false;

		self::write_log('Starting CardDAV-Addressbook synchronization');

		$carddav_backend = new carddav_backend($server['url']);
		$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));

		if ($carddav_backend->check_connection())
		{
			self::write_log('Connected to the CardDAV-Server ' . $server['url']);

			if ($vcard_id !== null)
			{
				$elements = $carddav_backend->get_xml_vcard($vcard_id);

				if ($carddav_contact_id !== null)
				{
					$carddav_addressbook_contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
					$carddav_addressbook_contacts = array(
						$carddav_addressbook_contact['vcard_id'] => $carddav_addressbook_contact
					);
				}
			}
			else
			{
				$elements = $carddav_backend->get(false);
				$carddav_addressbook_contacts = $this->get_carddav_addressbook_contacts();
			}

			try
			{
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

								if ($this->carddav_addressbook_update($carddav_content))
								{
									$any_data_synced = true;
								}
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
								if ($this->carddav_addressbook_add($carddav_content))
								{
									$any_data_synced = true;
								}
							}
						}

						unset($carddav_addressbook_contacts[$element_id]);
					}
				}
				else
				{
					$logging_message = 'No CardDAV XML-Element found!';
					if ($carddav_contact_id !== null && $vcard_id !== null)
					{
						self::write_log($logging_message . ' The CardDAV-Server does not have a contact with the vCard id ' . $vcard_id);
					}
					else
					{
						self::write_log($logging_message . ' The CardDAV-Server seems to have no contacts');
					}
				}

				if (!empty($carddav_addressbook_contacts))
				{
					foreach ($carddav_addressbook_contacts as $vcard_id => $etag)
					{
						if ($this->carddav_addressbook_delete($vcard_id))
						{
							$any_data_synced = true;
						}
					}
				}

				if ($any_data_synced === false)
				{
					self::write_log('all CardDAV-Data are synchronous, nothing todo!');
				}

				self::write_log('Syncronization complete!');
			}
			catch (Exception $e)
			{
				self::write_log('CardDAV-Server XML-Response is malformed. Synchronization aborted!');
				return false;
			}
		}
		else
		{
			self::write_log('Couldn\'t connect to the CardDAV-Server ' . $server['url']);
			return false;
		}

		return true;
	}

	/**
	 * Adds a vCard to the CardDAV addressbook
	 *
	 * @param	array	$carddav_content	CardDAV contents (vCard id, etag, last modified, etc.)
	 * @return	boolean
	 */
	private function carddav_addressbook_add($carddav_content)
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
			$this->carddav_server_id,
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
			self::write_log('Added CardDAV-Contact to the local database with the vCard id ' . $carddav_content['vcard_id']);
			return true;
		}
		else
		{
			self::write_log('Couldn\'t add CardDAV-Contact to the local database with the vCard id ' . $carddav_content['vcard_id']);
			return false;
		}

	}

	/**
	 * Updates a vCard in the CardDAV-Addressbook
	 *
	 * @param	array	$carddav_content	CardDAV contents (vCard id, etag, last modified, etc.)
	 * @return	boolean
	 */
	private function carddav_addressbook_update($carddav_content)
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
			$this->carddav_server_id,
			$rcmail->user->data['user_id']
		);

		if ($rcmail->db->affected_rows($result))
		{
			self::write_log('CardDAV-Contact updated in the local database with the vCard id ' . $carddav_content['vcard_id']);
			return true;
		}
		else
		{
			self::write_log('Couldn\'t update CardDAV-Contact in the local database with the vCard id ' . $carddav_content['vcard_id']);
			return false;
		}
	}

	/**
	 * Deletes a vCard from the CardDAV addressbook
	 *
	 * @param	string	$vcard_id	vCard id
	 * @return	boolean
	 */
	private function carddav_addressbook_delete($vcard_id)
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

		$result = $rcmail->db->query($query, $vcard_id, $this->carddav_server_id, $rcmail->user->data['user_id']);

		if ($rcmail->db->affected_rows($result))
		{
			self::write_log('CardDAV-Contact deleted from the local database with the vCard id ' . $vcard_id);
			return true;
		}
		else
		{
			self::write_log('Couldn\'t delete CardDAV-Contact from the local database with the vCard id ' . $vcard_id);
			return false;
		}
	}

	/**
	 * Adds a CardDAV server contact
	 *
	 * @param	string	$vcard	vCard
	 * @return	boolean
	 */
	private function carddav_add($vcard)
	{
		$rcmail = rcmail::get_instance();
		$server = current(carddav::get_carddav_server($this->carddav_server_id));
		$carddav_backend = new carddav_backend($server['url']);
		$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));

		if ($carddav_backend->check_connection())
		{
			$vcard_id = $carddav_backend->add($vcard);
			$this->carddav_addressbook_sync($server, false, $vcard_id);

			return $rcmail->db->insert_id(get_table_name('carddav_contacts'));
		}

		return false;
	}

	/**
	 * Updates a CardDAV server contact
	 *
	 * @param	integer		$carddav_contact_id		CardDAV contact id
	 * @param	string		$vcard					The new vCard
	 * @return	boolean
	 */
	private function carddav_update($carddav_contact_id, $vcard)
	{
		$rcmail = rcmail::get_instance();
		$contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
		$server = current(carddav::get_carddav_server($this->carddav_server_id));
		$carddav_backend = new carddav_backend($server['url']);
		$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));

		if ($carddav_backend->check_connection())
		{
			$carddav_backend->update($vcard, $contact['vcard_id']);
			$this->carddav_addressbook_sync($server, $carddav_contact_id, $contact['vcard_id']);

			return true;
		}

		return false;
	}

	/**
	 * Deletes the CardDAV server contact
	 *
	 * @param	array	$carddav_contact_ids	CardDAV contact ids
	 * @return	mixed							affected CardDAV contacts or false
	 */
	private function carddav_delete($carddav_contact_ids)
	{
		$rcmail = rcmail::get_instance();
		$server = current(carddav::get_carddav_server($this->carddav_server_id));
		$carddav_backend = new carddav_backend($server['url']);
		$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));

		if ($carddav_backend->check_connection())
		{
			foreach ($carddav_contact_ids as $carddav_contact_id)
			{
				$contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
				$carddav_backend->delete($contact['vcard_id']);
				$this->carddav_addressbook_sync($server, $carddav_contact_id, $contact['vcard_id']);
			}

			return count($carddav_contact_ids);
		}

		return false;
	}

	/**
	 * @see		rcube_addressbook::list_groups()
	 * @param	string	$search
	 * @return	boolean
	 */
	public function list_groups($search = null)
	{
		return false;
	}

	/**
	 * Returns a list of CardDAV adressbook contacts
	 *
	 * @param	string				$columns		Database columns
	 * @param	integer				$subset			Subset for result limits
	 * @return	rcube_result_set	$this->result	List of CardDAV adressbook contacts
	 */
	public function list_records($columns = null, $subset = 0)
	{
		$this->result = $this->count();
		$limit = array(
			'start' => ($subset < 0 ? $this->result->first + $this->page_size + $subset : $this->result->first),
			'length' => ($subset != 0 ? abs($subset) : $this->page_size)
		);

		$contacts = $this->get_carddav_addressbook_contacts($limit);

		if (!empty($contacts))
		{
			foreach ($contacts as $contact)
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
	 * Search and autocomplete contacts in the mail view
	 *
	 * @return	rcube_result_set	$this->result	List of searched CardDAV adressbook contacts
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
	 * Search method (autocomplete, addressbook)
	 *
	 * @param	array		$fields		Search in these fields
	 * @param	string		$value		Search value
	 * @param	boolean		$strict
	 * @param	boolean		$select
	 * @param	boolean		$nocount
	 * @param	array		$required
	 * @return rcube_result_set			List of searched CardDAV-Adressbook contacts
	 */
	public function search($fields, $value, $strict = false, $select = true, $nocount = false, $required = array())
	{
		$this->set_filter($fields, $value);
		return $this->search_carddav_addressbook_contacts();
	}

	/**
	 * Count CardDAV contacts for a specified CardDAV addressbook and return the result set
	 *
	 * @return	rcube_result_set
	 */
	public function count()
	{
		$count = $this->get_carddav_addressbook_contacts_count();
		return new rcube_result_set($count, ($this->list_page - 1) * $this->page_size);
	}

	/**
	 * @see		rcube_addressbook::get_record_groups()
	 * @param	integer		$id
	 * @return	boolean
	 */
	public function get_record_groups($id)
	{
		return false;
	}

	/**
	 * @see		rcube_addressbook::create_group()
	 * @param	string		$name
	 * @return	boolean
	 */
	public function create_group($name)
	{
		return false;
	}

	/**
	 * @see		rcube_addressbook::delete_group()
	 * @param	integer		$gid
	 * @return	boolean
	 */
	public function delete_group($gid)
	{
		return false;
	}

	/**
	 * @see	rcube_addressbook::rename_group()
	 * @param	integer		$gid
	 * @param	string		$newname
	 * @return	boolean
	 */
	public function rename_group($gid, $newname)
	{
		return false;
	}

	/**
	 * @see		rcube_addressbook::add_to_group()
	 * @param	integer		$group_id
	 * @param	array		$ids
	 * @return	boolean
	 */
	public function add_to_group($group_id, $ids)
	{
		return false;
	}

	/**
	 * @see		rcube_addressbook::remove_from_group()
	 * @param	integer		$group_id
	 * @param	array		$ids
	 * @return	boolean
	 */
	public function remove_from_group($group_id, $ids)
	{
		 return false;
	}

	/**
	 * Creates a new CardDAV addressbook contact
	 *
	 * @param	array		$save_data	Associative array with save data
	 * @param	boolean		$check		Check if the e-mail address already exists
	 * @return	mixed					The created record ID on success or false on error
	 */
	function insert($save_data, $check = false)
	{
		$rcmail = rcmail::get_instance();

		if (!is_array($save_data))
		{
			return false;
		}

		if ($check !== false)
		{
			foreach ($save_data as $col => $values)
			{
				if (strpos($col, 'email') === 0)
				{
					foreach ((array)$values as $email)
					{
						if ($existing = $this->search('email', $email, false, false))
						{
							break 2;
						}
					}
				}
			}
		}

		$database_column_contents = $this->get_database_column_contents($save_data);
		return $this->carddav_add($database_column_contents['vcard']);
	}

	/**
	 * Updates a CardDAV addressbook contact
	 *
	 * @param	integer		$carddav_contact_id		CardDAV contact id
	 * @param	array		$save_data				vCard parameters
	 * @return	boolean
	 */
	public function update($carddav_contact_id, $save_data)
	{
		$record = $this->get_record($carddav_contact_id, true);
		$database_column_contents = $this->get_database_column_contents($save_data, $record);

		return $this->carddav_update($carddav_contact_id, $database_column_contents['vcard']);
	}

	/**
	 * Deletes one or more CardDAV addressbook contacts
	 *
	 * @param	array   	$carddav_contact_ids	Record identifiers
	 * @param	boolean		$force
	 * @return	boolean
	 */
	public function delete($carddav_contact_ids, $force = true)
	{
		if (!is_array($carddav_contact_ids))
		{
			$carddav_contact_ids = explode(self::SEPARATOR, $carddav_contact_ids);
		}
		return $this->carddav_delete($carddav_contact_ids);
	}

	/**
	 * Convert vCard changes and return database relevant fileds including contents
	 *
	 * @param	array	$save_data					New vCard values
	 * @param	array 	$record						Original vCard
	 * @return	array	$database_column_contents	Database column contents
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

	/**
	 * Extended write log with pre defined logfile name and add version before the message content
	 *
	 * @param	string	$message	Log message
	 * @return	void
	 */
	public function write_log($message)
	{
		carddav::write_log(' carddav_server_id: ' . $this->carddav_server_id . ' | ' . $message);
	}
}
