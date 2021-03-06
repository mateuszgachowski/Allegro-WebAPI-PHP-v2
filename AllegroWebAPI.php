<?php

/**
 * Allegro SOAP API Class
 * 
 * @example
 * $api = new AllegroAPI(); // pass TRUE as first parameter for sandbox mode
 * $api->connect('allegroUserName', 'allegroPassword', 'allegroAPIKey');
 * $api->doGetMyNotSoldItems();
 * 
 * @uses nusoap Library (http://sourceforge.net/projects/nusoap/)
 * @license license url license name
 * @link http://allegro.pl/webapi/ Allegro SOAP API
 * @version 0.1.0
 * 
 * @author Mateusz Gachowski <mateusz.gachowski@gmail.com>
 * @license https://github.com/mateuszgachowski/Allegro-WebAPI-PHP-v2/blob/master/LICENSE MIT License
 */
class AllegroWebAPI {

  const   SANDBOX_URL    = 'https://webapi.allegro.pl.webapisandbox.pl/service.php?wsdl';
  const   PRODUCTION_URL = 'https://webapi.allegro.pl/service.php?wsdl';

  private $allowedDurations = array(3, 5, 7, 10, 14);

  private $isSandbox;

  private $APIUrl;

  private $soapClient;
  private $versionKeys = array();
  private $loginSession;

  // Optional 1, Allegro Shop
  private $countryCode = 1;

  protected $credentials;



  function __construct($sandbox = false) {
    $this->isSandbox = $sandbox;

    if ($this->isSandbox) {
      $this->APIUrl = self::SANDBOX_URL;
    }
    else {
      $this->APIUrl = self::PRODUCTION_URL;
    }
  }

  /**
   * Gets all version keys and store them into the variable
   * This method it required for 'localVersion' parameter pending login
   */
  private function _gatherVersionKeys() {
    $systemStatus = $this->soapClient->call(
      'doQueryAllSysStatus',
      array(
        array(
          'countryId' => $this->countryCode,
          'webapiKey' => $this->credentials->apiKey
        )
      )
    );

    foreach ($systemStatus['sysCountryStatus']['item'] as $item) {
      $this->versionKeys[$item['countryId']] = $item;
    }
  }

  /**
   * Gets current session and store it into the variable
   * Login the user in with given credentials 
   */
  private function _getSession() {
    $this->loginSession = $this->soapClient->call(
      'doLoginEnc',
      array(
        array(
          'userLogin'         => $this->credentials->userLogin,
          'userHashPassword'  => $this->credentials->userPassword,
          'webapiKey'         => $this->credentials->apiKey,
          'countryCode'       => $this->countryCode,
          'localVersion'      => $this->versionKeys[$this->countryCode]['verKey']
        )
      )
    );
  }

  /**
   * Simple method wrapper for all SOAP calls
   */
  private function _methodWrapper($methodName, $params) {
    return $this->soapClient->call(
      $methodName,
      array(
        $params
      )
    );
  }

  /**
   * Returns the best duration basing on given param
   * 
   * Returns 3 if no better duration found
   * 
   * @example
   * $api->bestAllowedDuration(5); // => 5
   * $api->bestAllowedDuration(14); // => 14
   * $api->bestAllowedDuration(9); // => 3
   * $api->bestAllowedDuration(3); // => 3
   * $api->bestAllowedDuration(999); // => 3
   * 
   * @param  Integer $duration Duration in days
   * @return Integer           Best duration
   */
  public function bestAllowedDuration($duration) {
    $duration = array_search((int)$duration, $this->allowedDurations);

    if (!$duration) {
        return 3;
    }
    else {
        return $this->allowedDurations[$duration];
    }
  }


  /**
   * Connects to the WebAPI
   * 
   * @param  String $userLogin    User login
   * @param  String $userPassword User Password
   * @param  String $apiKey       API Key generated by the Allegro (@see http://allegro.pl/myaccount/webapi.php)
   */
  public function connect($userLogin, $userPassword, $apiKey) {
    $this->soapClient = new nusoap_client($this->APIUrl, true);

    $this->credentials = new StdClass();
    $this->credentials->userLogin     = $userLogin;
    $this->credentials->userPassword  = base64_encode(hash('sha256', $userPassword, true));
    $this->credentials->apiKey        = $apiKey;

    $this->_gatherVersionKeys();
    $this->_getSession();

  }

  /**
   * Setter for countryCode / countryId
   * 
   * @example
   * $api = new AllegroAPI(true);
   * $api->setCountryId(2); // must be called before 'connect' method
   * $api->connect([...]);
   * 
   * @param Integer $countryId Country Id, default is 1
   */
  public function setCountryId($countryId) {
    $this->countryCode = $countryCode;
  }

  /**
   * Helpful methods
   */

  /**
   * Publishes all not sold items again
   * Duration of the auction will be the same as the previous one, if it fits allowed durations
   * 
   * @example
   * $api = new AllegroAPI(true);
   * $api->connect('allegroUserName', 'allegroPassword', 'allegroAPIKey');
   * $api->republishNotSoldItems(); // List of not sold items is empty right now. Skipping.
   * 
   * @return String/Array Output array or String error message
   */
  public function republishNotSoldItems() {
    $myNotSoldItems = $this->doGetMyNotSoldItems();

    $responses = array();

    if ($myNotSoldItems['notSoldItemsList']) {
      foreach ($myNotSoldItems['notSoldItemsList'] as $item) {
        $startDate = new DateTime();
        $endDate   = new DateTime();

        $startDate->setTimestamp((int)$item['itemStartTime']);
        $endDate->setTimestamp((int)$item['itemEndTime']);

        $auctionDuration = $endDate->diff($startDate);

        $response = $this->doSellSomeAgain(array(
          'itemId'   => $item['itemId'],
          'duration' => $auctionDuration->days,
          'sellStartingTime' => 0,
          'sellOption' => 1
        ));

        array_push($responses, $response);
      }

      return $responses;
    }
    else {
      return 'List of not sold items is empty right now. Skipping.';
    }
  }



  /**
   * Allegro API Methods (http://allegro.pl/webapi/documentation.php)
   * 
   * @link http://allegro.pl/webapi/documentation.php Allegro WebAPI Reference
   */

  /**
   * Gets all not sold items
   * 
   * @return Array Output
   */
  public function doGetMyNotSoldItems() {
    return $this->_methodWrapper(__FUNCTION__, array(
      'sessionId' => $this->loginSession['sessionHandlePart']
    ));
  }

  /**
   * Sells again an item using given params
   * 
   * @param Array $optiosn                      Options array
   * @param Integer $options.itemId             Id of the item to be republished
   * @param Integer $options.duration           Duration of the auction
   * @param Integer $options.sellStartingTime   Timestamp of date when auction should start
   * @param Integer $options.sellOption         1, 2 or 3, @link http://allegro.pl/webapi/documentation.php/show/id,1130 
   * 
   * @return Array Output data
   */
  public function doSellSomeAgain($options) {
    return $this->_methodWrapper(__FUNCTION__, array(
      'sessionHandle'       => $this->loginSession['sessionHandlePart'],
      'sellItemsArray'      => array('item' => $options['itemId']),
      'sellAuctionDuration' => $this->bestAllowedDuration($options['duration']),
      'sellStartingTime'    => $options['sellStartingTime'],
      'sellOptions'         => $options['sellOption']
    ));
  }
}