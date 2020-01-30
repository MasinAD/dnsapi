#!/usr/bin/php
<?php
if(!defined( 'DNS_DEBUG' ))   define('DNS_DEBUG', 5 );
if(!defined( 'DNS_INFO' ))    define('DNS_INFO', 4 );
if(!defined( 'DNS_NOTICE' ))  define('DNS_NOTICE', 3 );
if(!defined( 'DNS_WARNING' )) define('DNS_WARNING', 2 );
if(!defined( 'DNS_ERROR' ))   define('DNS_ERROR', 1 );
if(!defined( 'DNS_SILENT' ))   define('DNS_ERROR', 0 );

require_once('libdnsapi.php');
$debug_level = 5;
// We don't want to send anything before the request has been
// constructed.
$core = new DNSApi();
dns_info("Test startet");
#print_r($core->yaml);

if($core->providedBy('Core Networks') ) {
  dns_info("We got a provider!\n");
} else {
  dns_info("Noooo provider!");
}
try{
  if($core->auth( [ 'login' => $login, 'password' => $password ] ) ) {
    dns_info("Authenticated! Now fetching DNS zones …");
    $zones = $core->getDnsZones();
#    print_r($zones['response']);

    $new_record = [ 'name' => 'berlin',
                    'type' => 'CNAME',
                    'data' => 'snape.wikimedia.de' ];
    $add = $core->deleteDnsRecords( 'digital-o-mat.de', $new_record ); #print_r($add);
    foreach($zones['response'] as $zone) {
#      $z = $core->getDnsZone($zone['name']);
      $r = $core->getDnsRecordlist($zone['name']);
#      print_r($z['response']);
      print_r($r['response']);
#      print_r($r);
#      printf( "Zone:\n%s", print_r($zone) );
    }
  } else {
    dns_info("Nooooes!");
  }
} catch (Exception $xcptn) {
}

function dns_debug($message) {
  dns_log( 5, $message);
}
function dns_info($message) {
  dns_log( 4, $message);
}
function dns_notice($message) {
  dns_log( 3, $message);
}
function dns_warning($message) {
  dns_log( 2, $message);
}
function dns_error($message) {
  dns_log( 1, $message);
}

function dns_log( $lvl, $message ) {
  global $debug_level;
  if($lvl > $debug_level) return;
  switch(true) {
    case ($lvl == DNS_DEBUG):
      printf("Info: %s\n", $message);
      return;
    case ($lvl == DNS_INFO):
      printf("Info: %s\n", $message);
      return;
    case ($lvl == DNS_NOTICE):
      printf("Notice: %s\n", $message);
      return;
    case ($lvl == DNS_WARNING):
      printf("Warning: %s\n", $message);
      return;
    case ($lvl == DNS_ERROR):
      printf("Error: %s\n", $message);
      return;
  }
  return;
}