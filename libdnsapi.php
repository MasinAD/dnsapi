#!/usr/bin/php
<?php
class DNSApi {
  private $status;
  private $token;
  public $yaml;
  private $provider;

  /**
   * Constructor
   *
   * @param string  $file(optional)  Filename to lookup the API config
   */
  function __construct( $file = 'api.yaml' ) {
    if('' != $file && file_exists($file) ) {
      $this->yaml = yaml_parse_file($file)['config'];
      $this->state = 'instantiated';
    } else {
      return false;
    }
  }

  function providedBy($provider){
    if( isset($this->yaml['Provider'][$provider]) ) {
      $this->provider = $provider;
      return true;
    } else {
      return false;
    }
  }

  /**
   * Private method to check if the provided var is of the
   * expected type.
   *
   * @param mixed $var  The variable to check
   * @param string  $type The expected type
   *
   * @return  boolean
   */
  private function checkType($var, $type) {
    if( gettype($var) == $type ) return true;
    return false;
  }

/*
 * Anfrage
 * curl --request POST --data '{"login":"%USERNAME%","password":
 * "%PASSWORD%"}' https://beta.api.core-networks.de/auth/token
 * Antwort
 * {"token":"%TOKEN%","expires":%EXPIRES%}
 */

  /**
   * Method to authenticate agains the API server.
   *
   * @param array $authdata Array of the authentication credentials
   *
   * @return  boolean Whether the authentication succeeded or not.
   */
  function auth( $authdata ) {
    dns_notice("Entering " . __FUNCTION__);
// Do we have a valid authentication token?
    if( $token = $this->loadAuth() ) {
// TODO: We should not only propagate the token but also the expiry time
// and check it every time.
      $this->token = $token;
      $this->state = 'authenticated';
      return true;
    }
// Ok, let's get one.
    if('array' == strtolower( gettype($authdata) ) ) {
      $provider = $this->yaml['Provider'][$this->provider];
      $auth = $provider['endpoints'][__FUNCTION__];
      $payload = [];
      foreach(['parameters', 'optional'] as $params) {
        if(isset($auth[$params]) ) {
          foreach($auth[$params] as $name => $type) {
            if( $this->checkType($authdata[$name], $type) )
              $auth[$params][$name] = $authdata[$name];
            else throw new Exception("${name} has invalid data type:
              ${type} expected, " .gettype($authdata[$name]). " received." );
            $payload = array_merge($payload, $auth[$params]);
          }
        }
      }
      $request = $this->request( $auth, $payload );

      if(200 == $request['result']['http_code']) {
        $response = $this->saveAuth($request['response']);
        $this->token = $token;
        $this->state = 'authenticated';
        return true;
      } else {
        throw new Exception("Auth failed with http code ${auth_result['http_code']}");
      }
    } else {
      return false;
    }
  }

  /**
   * Private method to care about the correctness of the API request.
   *
   * @param string  $call The URL to call
   * @param array  $payload(optional) The payload necessary for the API call.
   *
   * @return  array The response and result of the API call.
   */
  private function request( $call, $payload = false ) {
    dns_notice("Entering " . __FUNCTION__);
    $provider = $this->yaml['Provider'][$this->provider];
    $url = $provider['url'];
    $request = curl_init( $url . $call['path'] );
    if( 'post' == strtolower( $call['method'] ) ) {
      curl_setopt($request, CURLOPT_POST, true);
    }
    if( 'authenticated' == $this->state) {
      curl_setopt($request, CURLOPT_HTTPHEADER, [
        sprintf($provider['auth_header'], $this->token)
      ]);
    }
    curl_setopt($request, CURLINFO_HEADER_OUT, true);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    if( $payload ) {
      curl_setopt($request, CURLOPT_POSTFIELDS, json_encode($payload) );
      #print_r($payload);
    }
    $response = curl_exec($request);
    $result = curl_getinfo($request);
    curl_close($request);
    return [ 'result' => $result, 'response' => json_decode($response, true) ];
  }

  /**
   * Will return an array of zones.
   *
   * @return  array The response and result of the API call.
   */
  function getDnsZones() {
    dns_notice("Entering " . __FUNCTION__);
    if($this->state !== 'authenticated') {
      throw new Exception("Need to be authenticated first.");
    }
    $provider = $this->yaml['Provider'][$this->provider];
    $get = $provider['endpoints'][__FUNCTION__];
    $result = $this->request( $get );
    return($result);
  }

  /**
   * Will return basic information about the provided zone.
   *
   * @param string  $zone The zone to work on.
   *
   * @return  array The response and result of the API call.
   */
  function getDnsZone( $zone ) {
    dns_notice("Entering " . __FUNCTION__);
    if($this->state !== 'authenticated') {
      throw new Exception("Need to be authenticated first.");
    }
    $provider = $this->yaml['Provider'][$this->provider];
    $get = $provider['endpoints'][__FUNCTION__];
    $get['path'] = sprintf( $get['path'], $zone);
    $result = $this->request( $get );
    return($result);
  }

  /**
   * Will return an array of records in the provided zone.
   *
   * @param string  $zone The zone to work on.
   * @param string  $type(optional) Which records to return.
   *
   * @return  array The response and result of the API call.
   */
  function getDnsRecordlist( $zone, $type = false ) {
    dns_notice("Entering " . __FUNCTION__);
    if($this->state !== 'authenticated') {
      throw new Exception("Need to be authenticated first.");
    }
    $provider = $this->yaml['Provider'][$this->provider];
    $get = $provider['endpoints'][__FUNCTION__];
    $get['path'] = sprintf( $get['path'], $zone);
    if($type) $get['path'] = $get['path'] . '?type=' . $type;
    $result = $this->request( $get );
    return($result);
  }

  /**
   * Private method used be setDnsRecord and deleteDnsRecord
   *
   * @param string  $function The name of the calling function.
   * @param string  $zone The zone to work on.
   * @param array   The record data to work with, either set the record pr delete records matching it.
   *
   * @return  array The response and result of the API call.
   */
  private function setOrDelete( $function, $zone, $record ){
    dns_notice("Entering " . __FUNCTION__);
    $provider = $this->yaml['Provider'][$this->provider];
    $get = $provider['endpoints'][$function];
    $payload = [];
    foreach(['parameters', 'optional'] as $params) {
      if(isset($get[$params]) ) {
        foreach($get[$params] as $name => $type) {
          if( isset($record[$name]) &&
              $this->checkType($record[$name], $type) )
            $get[$params][$name] = $record[$name];
          else {
            if( isset($record[$name]) ){
              throw new Exception("${name} has invalid data type:
              ${type} expected, " .gettype($record[$name]). " received." );
            } else {
              if('parameters' == $params) {
                throw new Exception("${name} required." );
              }
              unset($get[$params][$name]);
            }
          }
        }
        $payload = array_merge($payload, $get[$params]);
      }
    }
    $get['path'] = sprintf( $get['path'], $zone);
    $result = $this->request( $get, $payload );
    return($result);
  }

  /**
   * Method to set a single DNS record.
   *
   * @uses  setOrDelete
   *
   * @param string  $zone The name of the zone to work on.
   * @param array   $record The record to set.
   *
   * @return  array The result and the response of the API call.
   */
  function setDnsRecord( $zone, $record ) {
    dns_notice("Entering " . __FUNCTION__);
    if($this->state !== 'authenticated') {
      throw new Exception("Need to be authenticated first.");
    }

    return $this->setOrDelete(__FUNCTION__, $zone, $record);
  }

  /**
   * Method to delete DNS records. When not providing a complete
   * record it will delete all records matching the provided fields.
   *
   * @uses  setOrDelete
   *
   * @param string  $zone The name of the zone to work on.
   * @param array   $record The filter for the records to delete.
   *
   * @return  array The result and the response of the API call.
   */
  function deleteDnsRecords( $zone, $record ) {
    dns_notice("Entering " . __FUNCTION__);
    if($this->state !== 'authenticated') {
      throw new Exception("Need to be authenticated first.");
    }

    return $this->setOrDelete(__FUNCTION__, $zone, $record);
  }

  /**
   * Tells the API server to commit all changes into running state.
   *
   * @return  array The result and the response of the API call.
   */
  function commit() {
    dns_notice("Entering " . __FUNCTION__);
    if($this->state !== 'authenticated') {
      throw new Exception("Need to be authenticated first.");
    }
    $provider = $this->yaml['Provider'][$this->provider];
    $get = $provider['endpoints'][__FUNCTION__];
    $result = $this->request( $get );
    return($result);
  }


  /**
   * Private method to save token data to token store.
   * Token store usually is .token.json in the same folder.
   *
   * @param array $tokendata  The token data to store.
   *
   * @return  string  the token.
   */
  private function saveAuth($tokendata) {
    dns_notice("Entering " . __FUNCTION__);
    $token = $tokendata;
    print_r($token);
    $token['expires'] = time() + $token['expires'];
    $prvdr = str_replace(" ", "_", $this->provider);
    $filename = ".token.json";

    if(file_exists( $filename ) ) {
      $tokenfile = json_decode( file_get_contents( $filename ), true );
      $tokenfile = array_merge(
                     $tokenfile,
                     [ $this->provider => $token ]
                    );
    } else {
      $tokenfile = [ $this->provider => $token ];
    }
    file_put_contents( $filename, json_encode( $tokenfile ) );
    return $token['token'];
  }

  /**
   * Private method to load token data from token store.
   * Token store usually is .token.json in the same folder.
   *
   * @return  string|false  The token or false if none found
   */

  private function loadAuth() {
    dns_notice("Entering " . __FUNCTION__);
    $filename = ".token.json";

    if(file_exists( $filename ) ) {
      $tokenfile = json_decode( file_get_contents( $filename ), true );
      if( isset($tokenfile[ $this->provider ]) ) {
        $token = $tokenfile[ $this->provider ];
        # Give at least 10 seconds for the initiated request
        if( time()+10 < $token['expires'] ) return $token['token'];
        else return false;
      } else return false;
    } else return false;
  }
}
?>
