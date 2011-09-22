<?php

/**
 * cardDAV-PHP
 *
 * simple cardDAV query
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
 * check cardDAV-Server connection
 * -------------------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * var_dump($carddav->check_connection());
 *
 *
 * cardDAV delete query
 * --------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->delete('0126FFB4-2EB74D0A-302EA17F');
 * 
 * 
 * cardDAV add query
 * --------------------
 * $vcard = 'BEGIN:VCARD
 * VERSION:3.0
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
 *  cardDAV update query
 * --------------------
 * $vcard = 'BEGIN:VCARD
 * VERSION:3.0
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
 * 
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Graviox Studios
 * @link http://www.graviox.de
 * @since 20.07.2011
 * @version 0.32
 * @license http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
 * 
 */

class carddav_backend
{
	/**
	 * cardDAV-Server url
	 *
	 * @var string
	 */
	protected $url = null;
	
	/**
	 * base64 encoded authentification information
	 * 
	 * @var string
	 */
	protected $auth = null;
	
	/**
	 * characters used for vCard id generation
	 * 
	 * @var array
	 */
	protected $vcard_id_chars = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F');
	
	/**
	 * http request context
	 * 
	 * @var array
	 */
	protected $context = array();
	
	/**
	 * user agent displayed in http requests
	 * 
	 * @var string
	 */
	protected $user_agent = 'cardDAV-PHP/0.32';

	/**
	 * set the cardDAV-Server url
	 * 
	 * @param string $url cardDAV-Server url
	 */
	public function __construct($url)
	{
		$this->url = $url;
	}
	
	/**
	 * set authentification information and base64 encode them
	 * 
	 * @param string $username cardDAV-Server username
	 * @param string $password cardDAV-Server password
	 */
	public function set_auth($username, $password)
	{
		$this->auth = base64_encode($username.':'.$password);
	}

	/**
	 * set http request context
	 *
	 * @param string $method HTTP-Method like (OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, COPY, MOVE)
	 * @param string $content content for cardDAV queries
	 * @param string $content_type set content-type
	 */
	private function set_context($method, $content = null, $content_type = null)
	{
		$context['http']['method'] = $method;
		$context['http']['header'][] = 'User-Agent: '.$this->user_agent;
		
		if ($content !== null)
		{
			$context['http']['content'] = $content;
		}
	
		if ($content_type !== null)
		{
			$context['http']['header'][] = 'Content-type: '.$content_type;
		}
	
		if ($this->auth !== null)
		{
			$context['http']['header'][] = 'Authorization: Basic '.$this->auth;
		}
	
		$this->context = stream_context_create($context);
	}
	
	/**
	 * get xml-response from the cardDAV-Server
	 * 
	 * @param boolean $include_vcards include vCards in the response (simplified only)
	 * @param boolean $raw get response raw or simplified
	 * @return string raw or simplified xml response
	 */
	public function get($include_vcards = true, $raw = false)
	{
		$xml = new XMLWriter();
		$xml->openMemory();
		$xml->setIndent(4);
		$xml->startDocument('1.0', 'utf-8');
			$xml->startElement('C:addressbook-query');
				$xml->writeAttribute('xmlns:D', 'DAV:');
				$xml->writeAttribute('xmlns:C', 'urn:ietf:params:xml:ns:carddav');
				$xml->startElement('D:prop');
					$xml->writeElement('D:getetag');
				$xml->endElement();
			$xml->endElement();
		$xml->endDocument();
		
		$this->set_context('REPORT', $xml->outputMemory(), 'text/xml');
		$response = $this->query($this->url);
		
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
	* get the response from the cardDAV-Server
	*
	* @param resource $stream cardDAV stream resource
	* @return string cardDAV xml-response
	*/
	private function get_response($stream)
	{
		$response_header = stream_get_meta_data($stream);
	
		foreach ($response_header['wrapper_data'] as $header)
		{
			if (preg_match('/Content-Length/', $header))
				$content_length = (int) str_replace('Content-Length: ', null, $header);
		}
	
		return stream_get_contents($stream, $content_length);
	}

	/**
	 * get a vCard from the cardDAV-Server
	 * 
	 * @param string $id vCard id on the cardDAV-Server
	 * @return string cardDAV xml-response
	 */
	public function get_vcard($vcard_id)
	{
		$vcard_id = str_replace('.vcf', null, $vcard_id);
		$this->set_context('GET');
		return $this->query($this->url.$vcard_id.'.vcf');
	}
	
	/**
	* checks if the cardDAV-Server is reachable
	*
	* @return boolean
	*/
	public function check_connection()
	{
		$this->set_context('OPTIONS', null, 'text/xml');
		$response = $this->query($this->url);
	
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
	 * deletes an entry from the cardDAV-Server
	 * 
	 * @param string $id vCard id on the cardDAV-Server
	 * @return string cardDAV xml-response
	 */
	public function delete($vcard_id)
	{
		$this->set_context('DELETE');
		return $this->query($this->url.$vcard_id.'.vcf');
	}
	
	/**
	 * adds an entry to the cardDAV-Server
	 *
	 * @param string $vcard vCard
	 * @param string $vcard_id vCard id on the cardDAV-Server
	 * @return string cardDAV xml-response
	 */
	public function add($vcard, $vcard_id = null)
	{
		if ($vcard_id === null)
		{
			$vcard_id = $this->generate_vcard_id();
		}
		
		$vcard = str_replace("\t", null, $vcard);
		$this->set_context('PUT', $vcard, 'text/vcard');
		return $this->query($this->url.$vcard_id.'.vcf');
	}
	
	/**
	 * updates an entry to the cardDAV-Server
	 *
	 * @param string $vcard vCard
	 * @param string $id vCard id on the cardDAV-Server
	 * @return string cardDAV xml-response
	 */
	public function update($vcard, $vcard_id)
	{
		$vcard_id = str_replace('.vcf', null, $vcard_id);
		return $this->add($vcard, $vcard_id);
	}
	
	/**
	 * simplify cardDAV xml-response
	 *
	 * @param string $response cardDAV xml-response
	 * @return string simplified cardDAV xml-response
	 */
	private function simplify($response, $include_vcards = true)
	{
		$response = str_replace('VC:address-data', 'vcard', $response);
		$url = parse_url($this->url);
		$xml = new SimpleXMLElement($response);
		
		$simplified_xml = new XMLWriter();
		$simplified_xml->openMemory();
		$simplified_xml->setIndent(4);
		
		$simplified_xml->startDocument('1.0', 'utf-8');
			$simplified_xml->startElement('response');
			
				foreach ($xml->response as $response)
				{
					$id = str_replace($url['path'], null, $response->href);
					
					if (!empty($id))
					{
						$simplified_xml->startElement('element');
						$simplified_xml->writeElement('id', $id);
						$simplified_xml->writeElement('etag', str_replace('"', null, $response->propstat->prop->getetag));
						
						if ($include_vcards === true)
						{
							$simplified_xml->writeElement('vcard', $this->get_vcard($id));
						}
							
						$simplified_xml->endElement();
					}
				}
			
			$simplified_xml->endElement();
		$simplified_xml->endDocument();
		
		return $simplified_xml->outputMemory();
	}
	
	/**
	 * quries the cardDAV-Server and returns the response
	 * 
	 * @param string $url cardDAV-Server url
	 * @return string cardDAV xml-response
	 */
	private function query($url)
	{
		if ($stream = fopen($url, 'r', false, $this->context))
		{
			return $this->get_response($stream);
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

		$cardDAV = new carddav_backend($this->url);
		$cardDAV->auth = $this->auth;
		$cardDAV->set_context('GET');
		
		if ($cardDAV->query($this->url.$id.'.vcf') === false)
		{
			return $id;
		}
		else
		{
			return $this->generate_vcard_id();
		}
	}
}
