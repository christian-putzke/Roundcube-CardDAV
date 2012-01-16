<?php

/**
 * CardDAV-PHP
 *
 * simple CardDAV query
 * --------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * echo $carddav->get();
 * 
 * 
 * simple vCard query
 * ------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * echo $carddav->get_vcard('0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * check CardDAV-Server connection
 * -------------------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * var_dump($carddav->check_connection());
 *
 *
 * CardDAV delete query
 * --------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->delete('0126FFB4-2EB74D0A-302EA17F');
 * 
 * 
 * CardDAV add query
 * --------------------
 * $vcard = 'BEGIN:VCARD
 * VERSION:3.0
 * UID:1f5ea45f-b28a-4b96-25as-ed4f10edf57b
 * FN:Christian Putzke
 * N:Christian;Putzke;;;
 * EMAIL;TYPE=OTHER:christian.putzke@graviox.de
 * END:VCARD';
 * 
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->add($vcard);
 * 
 * 
 * CardDAV update query
 * --------------------
 * $vcard = 'BEGIN:VCARD
 * VERSION:3.0
 * UID:1f5ea45f-b28a-4b96-25as-ed4f10edf57b
 * FN:Christian Putzke
 * N:Christian;Putzke;;;
 * EMAIL;TYPE=OTHER:christian.putzke@graviox.de
 * END:VCARD';
 * 
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->update($vcard, '0126FFB4-2EB74D0A-302EA17F');
 * 
 * 
 * URL-Schema list
 * ---------------
 * DAViCal: https://example.com/{resource|principal}/{collection}/
 * Apple Addressbook Server: https://example.com/addressbooks/users/{resource|principal}/{collection}/ 
 * memotoo: https://sync.memotoo.com/cardDAV/
 * SabreDAV: https://demo.sabredav.org/addressbooks/{resource|principal}/{collection}/
 * ownCloud: https://example.com/apps/contacts/carddav.php/addressbooks/{username}/default/
 * 
 * 
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Graviox Studios
 * @link http://www.graviox.de
 * @since 20.07.2011
 * @version 0.4.4
 * @license http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
 * 
 */

class carddav_backend
{
	/**
	 * CardDAV-Server url
	 *
	 * @var string
	 */
	private $url = null;
	
	/**
	 * authentification information
	 * 
	 * @var string
	 */
	private $auth = null;
	
	/**
	 * characters used for vCard id generation
	 * 
	 * @var array
	 */
	private $vcard_id_chars = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F');
	
	/**
	 * user agent displayed in http requests
	 * 
	 * @var string
	 */
	private $user_agent = 'CardDAV-PHP/0.4.4';
	
	/**
	 * constructor
	 * set the CardDAV-Server url
	 * 
	 * @param string $url CardDAV-Server url
	 */
	public function __construct($url = null)
	{
		if ($url !== null)
		{
			$this->set_url($url);
		}
	}
	
	/**
	* set the CardDAV-Server url
	*
	* @param string $url CardDAV-Server url
	*/
	public function set_url($url)
	{
		$this->url = $url;
		
		if (substr($this->url, -1, 1) !== '/')
		{
			$this->url = $this->url . '/';
		}
	}
	
	/**
	 * set authentification information
	 * 
	 * @param string $username CardDAV-Server username
	 * @param string $password CardDAV-Server password
	 */
	public function set_auth($username, $password)
	{
		$this->username = $username;
		$this->password = $password;
		$this->auth = $username . ':' . $password;
	}

	/**
	 * get propfind xml-response from the CardDAV-Server
	 * 
	 * @param boolean $include_vcards include vCards in the response (simplified only)
	 * @param boolean $raw get response raw or simplified
	 * @return string raw or simplified xml response
	 */
	public function get($include_vcards = true, $raw = false)
	{
		$response = $this->query($this->url, 'PROPFIND');
		
		if ($response === false || $raw === true)
		{
			return $response;
		}
		else
		{
			return $this->simplify($response, $include_vcards);
		}
	}
	
	/**
	 * get a vCard from the CardDAV-Server
	 * 
	 * @param string $id vCard id on the CardDAV-Server
	 * @return string vCard (text/vcard)
	 */
	public function get_vcard($vcard_id)
	{
		$vcard_id = str_replace('.vcf', null, $vcard_id);
		return $this->query($this->url . $vcard_id . '.vcf', 'GET');
	}
	
	/**
	* checks if the CardDAV-Server is reachable
	*
	* @return boolean
	*/
	public function check_connection()
	{
		$response = $this->query($this->url, 'OPTIONS');
		
		if ($response === false)
		{
			return false;
		}
		else
		{
			return true;
		}
	}
	
	/**
	 * deletes an entry from the CardDAV-Server
	 * 
	 * @param string $id vCard id on the CardDAV-Server
	 * @return string CardDAV xml-response
	 */
	public function delete($vcard_id)
	{
		return $this->query($this->url . $vcard_id . '.vcf', 'DELETE');
	}
	
	/**
	 * adds an entry to the CardDAV-Server
	 *
	 * @param string $vcard vCard
	 * @param string $vcard_id vCard id on the CardDAV-Server
	 * @return string CardDAV xml-response
	 */
	public function add($vcard, $vcard_id = null)
	{
		if ($vcard_id === null)
		{
			$vcard_id = $this->generate_vcard_id();
		}
		
		$vcard = str_replace("\t", null, $vcard);
		return $this->query($this->url . $vcard_id . '.vcf', 'PUT', $vcard, 'text/vcard');
	}
	
	/**
	 * updates an entry to the CardDAV-Server
	 *
	 * @param string $vcard vCard
	 * @param string $id vCard id on the CardDAV-Server
	 * @return string CardDAV xml-response
	 */
	public function update($vcard, $vcard_id)
	{
		$vcard_id = str_replace('.vcf', null, $vcard_id);
		return $this->add($vcard, $vcard_id);
	}
	
	/**
	 * simplify CardDAV xml-response
	 *
	 * @param string $response CardDAV xml-response
	 * @return string simplified CardDAV xml-response
	 */
	private function simplify($response, $include_vcards = true)
	{
		$response = $this->clean_response($response);
		
		$xml = new SimpleXMLElement($response);

		$simplified_xml = new XMLWriter();
		$simplified_xml->openMemory();
		$simplified_xml->setIndent(4);
		
		$simplified_xml->startDocument('1.0', 'utf-8');
			$simplified_xml->startElement('response');
			
				foreach ($xml->response as $response)
				{
					if (preg_match('/vcard/', $response->propstat->prop->getcontenttype) || preg_match('/vcf/', $response->href))
					{
						$id = basename($response->href);
						$id = str_replace('.vcf', null, $id);
						
						if (!empty($id))
						{
							$simplified_xml->startElement('element');
							$simplified_xml->writeElement('id', $id);
							$simplified_xml->writeElement('etag', str_replace('"', null, $response->propstat->prop->getetag));
							$simplified_xml->writeElement('last_modified', $response->propstat->prop->getlastmodified);
	
							if ($include_vcards === true)
							{
								$simplified_xml->writeElement('vcard', $this->get_vcard($id));
							}
								
							$simplified_xml->endElement();
						}
					}
				}
				
			$simplified_xml->endElement();
		$simplified_xml->endDocument();
		
		return $simplified_xml->outputMemory();
	}
	
	/**
	 * cleans CardDAV xml-response
	 * 
	 * @param string $response CardDAV xml-response
	 * @return string $response cleaned CardDAV xml-response
	 */
	private function clean_response($response)
	{
		$response = str_replace('D:', null, $response);
		$response = str_replace('d:', null, $response);
		
		return $response;
	}
	
	/**
	 * quries the CardDAV-Server via curl and returns the response
	 * 
	 * @param string $method HTTP-Method like (OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, COPY, MOVE)
	 * @param string $content content for CardDAV-Queries
	 * @param string $content_type set content-type
	 * @return string CardDAV xml-response
	 */
	private function query($url, $method, $content = null, $content_type = null)
	{
		$ch = curl_init($url);
		
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);

		if ($content !== null)
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		}
		
		if ($content_type !== null)
		{
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: '.$content_type));
		}
		
		if ($this->auth !== null)
		{
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_USERPWD, $this->auth);
		}
		
		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		curl_close($ch);
		
		if (in_array($http_code, array(200, 207)))
		{
			return $response;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * returns a valid and unused vCard id
	 * 
	 * @return string valid vCard id
	 */
	private function generate_vcard_id()
	{
		$id = null;
		
		for ($number = 0; $number <= 25; $number ++)
		{
			if ($number == 8 || $number == 17)
			{
				$id .= '-';
			}
			else
			{
				$id .= $this->vcard_id_chars[mt_rand(0, (count($this->vcard_id_chars) - 1))];
			}
		}

		$carddav = new carddav_backend($this->url);
		$carddav->set_auth($this->username, $this->password);
		
		if (!preg_match('/BEGIN:VCARD/', $carddav->query($this->url . $id . '.vcf', 'GET')))
		{
			return $id;
		}
		else
		{
			return $this->generate_vcard_id();
		}
	}
}
