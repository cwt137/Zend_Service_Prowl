<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Prowl
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */


/**
 * @see Zend_Service_Abstract
 */
require_once 'Zend/Service/Abstract.php';

/**
 * Prowl third-party API implementation
 *
 * @uses       Zend_Service_Abstract
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Prowl
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Service_Prowl extends Zend_Service_Abstract
{

    /**
     * API key(s)
     * @var string|array
     */
    protected $_apiKey;

    /**
     * Provider API key. Only necessary if you have been whitelisted.
     * @var string
     */
    protected $_providerKey;


    /**
     * Priority setting. Values -2 to 2 ok.
     * @var string
     */
    protected $_priority = 0;

    /**
     * The name of the application or the application generating the event.
     * @var string
     */
    protected $_applicationName = 'Zend Framework';

    /**
     * The name of the event or subject of the notification.
     * @var string
     */
    protected $_event = null;

    /**
     * A description of the event, generally terse.
     * @var string
     */
    protected $_description = null;

    /**
     * Error or Success code returned
     * @var integer
     */
    protected $_returnCode = null;

    /**
     * Error Message Returned
     * @var string
     */
    protected $_errorMessage = null;

    /**
     * Date the 1000 request counter resets
     * @var string
     */
    
    protected $_resetDate = null;

    /**
     * Remaining requests left before the reset date
     * @var integer
     */    
    protected $_remainingRequests = null;

    /**
     * Base URL of API service
     * @var string
     */
    const BASE_URL = 'https://prowl.weks.net/publicapi/';

    const PRIORITY_VERY_LOW = -2;
    const PRIORITY_MODERATE = -1;
    const PRIORITY_NORMAL = 0;
    const PRIORITY_HIGH = 1;
    const PRIORITY_EMERGENCY = 2;

    /**
     * Constructor
     *
     * @param string|array $apiKey API key(s). if array, up to 5 elements
     * @param string $providerKey Pprovider API key. Only necessary if you have been whitelisted.
     * @return void
     */
    public function __construct($apiKey, $providerKey = null)
    {
        $this->setApiKey($apiKey);
        $this->setProviderKey = $providerKey;

    }

    /**
     * Add a notification for a particular user.
     *
     * @return boolean
     */
    public function add() 
    {

        $params = array('priority' => $this->getPriority(),
                        'application' =>$this->getApplicationName(),
                        'event' => $this->getEvent(),
                        'description' => $this->getDescription());

        if ($this->getProviderKey() != null) {
            $params['providerkey'] = $this->getProviderKey();
        }

        if (is_array($this->getApiKey())) {
            $params['apikey'] = implode(',', $this->getApiKey());
        } else {
            $params['apikey'] = $this->getApiKey();
        }

        $uri = self::BASE_URL . 'add';
        $client = $this->getHttpClient();

        $client->setUri($uri);
        $client->setParameterPost($params);

        require_once 'Zend/Http/Client/Exception.php';
        try {
            $response = $client->request('POST');
            $xmlString = $response->getBody();

        } catch(Zend_Http_Client_Exception $e) {
            require_once 'Zend/Service/Exception.php';
            throw new Zend_Service_Exception("Service Request Failed: {$e->getMessage()}");
            return false;
        }

        $this->_parseResponseXML($xmlString);
    }

    /**
     * Verify an API key is valid.
     *
     * @return boolean
     */
    public function verify() 
    {
        $params = array('apikey' => $this->getApiKey());
        if($this->getProviderKey() != null) {
            $params['providerkey'] = $this->getProviderKey();
        }

        $uri = self::BASE_URL . 'verify';
        $client = $this->getHttpClient();

        $client->setUri($uri);
        $client->setParameterGet($params);

        require_once 'Zend/Http/Client/Exception.php';
        try {
            $response = $client->request();
            $xmlString = $response->getBody();

        } catch(Zend_Http_Client_Exception $e) {
            require_once 'Zend/Service/Exception.php';
            throw new Zend_Service_Exception("Service Request Failed: {$e->getMessage()}");
            return false;
        }

        $this->_parseResponseXML($xmlString);
    }

    /**
     * Parses XML response from server
     *
     * @param string $xmlString
     * @retun void
     */
    protected function _parseResponseXML($xmlString) 
    {
        require_once 'Zend/Service/Exception.php';
        try {
            $xml = new SimpleXMLElement($xmlString);
        } catch(Zend_Service_Exception $e) {
            throw new Zend_Service_Exception("Parsing XML response failed: {$e->getMessage()}");
            return;
        }

        //see what kind of response and parse it
        if (count($xml->xpath('//success')) > 0) {
            $results = $xml->xpath('//success');

            $returnCode = (int) $results[0]['code'];
            $this->_setReturnCode($returnCode);

            $this->_setErrorMessage(null);

            $resetDate = (int) $results[0]['resetdate'];
            $resetDate = date('c', $resetDate);
            $this->_setResetDate($resetDate);

            $remainingRequests = (int) $results[0]['remaining'];
            $this->_setRemainingRequests($remainingRequests);            

        } elseif (count($xml->xpath('//error')) > 0) {
            $results = $xml->xpath('//error');

            $returnCode = (int) $results[0]['code'];
            $this->_setReturnCode($returnCode);

            $errorMessage = (string) $results[0];
            $this->_setErrorMessage($errorMessage);
        } else {
            throw new Zend_Service_Exception("Parsing XML response failed");
        }
    }

    /**
     * Gets the API Key(s)
     *
     * @return string|array API key(s). if array, up to 5 elements
     */
    public function getApiKey() 
    {
        return $this->_apiKey;
    }

    /**
     * Sets the API Key(s)
     *
     * @param string|array $apiKey API key(s). if array, up to 5 elements
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->_apiKey = $apiKey;
    }

    /**
     * Sets Provider API key
     *
     * @return string
     */
    public function getProviderKey()
    {
        return $this->_providerKey;
    }

    /**
     * Gets Provider API key
     *
     * @param string $providerKey
     * @return
     */
    public function setProviderKey($providerKey)
    {
        $this->_providerKey = $providerKey;
    }    

    /**
     * Gets the priority setting
     *
     * @return integer
     */
    public function getPriority()
    {
        return $this->_priority;
    }

    /**
     * Sets the priority setting
     *
     * @param integer $priority
     * @return void
     */
    public function setPriority($priority)
    {
        $this->_priority = $priority;
    }

    /**
     * Gets the name of the application or the application generating the event.
     *
     * @return string
     */
    public function getApplicationName()
    {
        return $this->_applicationName;
    }

    /**
     * Sets the name of the application or the application generating the event.
     *
     * @param string $applicationName
     * @return
     */
    public function setApplicationName($applicationName)
    {
        $this->_applicationName = $applicationName;
    }

    /**
     * Get the name of the event or subject of the notification.
     *
     * @return
     */
    public function getEvent()
    {
        return $this->_event;
    }

    /**
     * Set the name of the event or subject of the notification.
     *
     * @param string $event
     * @return
     */
    public function setEvent($event)
    {
        $this->_event = $event;
    }

    /**
     * Gets the description of the event.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * Sets the description of the event.
     *
     * @param string $description
     * @return void
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * Gets error or success code returned
     *
     * @return integer
     */
    public function getReturnCode()
    {
        return $this->_returnCode;
    }

    /**
     * Sets error or success code returned
     *
     * @param integer $returnCode
     * @return
     */
    protected function _setReturnCode($returnCode)
    {
        $this->_returnCode = $returnCode;
    }

    /**
     * Gets error message returned
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->_errorMessage;
    }

    /**
     * Sets error message returned
     *
     * @param string $errorMessage
     * @return
     */
    protected function _setErrorMessage($errorMessage)
    {
        $this->_errorMessage = $errorMessage;
    }

    /**
     * Gets the date the 1000 request counter resets
     *
     * @return string
     */
    public function getResetDate()
    {
        if($this->_resetDate == null) {
            $this->verify();
        }
    
        return $this->_resetDate;
    }

    /**
     * Sets the date the 1000 request counter resets
     *
     * @param string $resetDate
     * @return
     */
    protected function _setResetDate($resetDate)
    {
        $this->_resetDate = $resetDate;
    }

    /**
     * Gets the remaining requests left before the reset date
     *
     * @return integer
     */
    public function getRemainingRequests()
    {
        if($this->_remainingRequests == null) {
            $this->verify();
        }
    
        return $this->_remainingRequests;
    }

    /**
     * Sets the remaining requests left before the reset date
     *
     * @param integer $remainingRequests
     * @return
     */
    protected function _setRemainingRequests($remainingRequests)
    {
        $this->_remainingRequests = $remainingRequests;
    }
}
