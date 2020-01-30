# dnsapi
Class for accessing the DNS API of Core Network

## Usage
```
$dns = new DNSApi();
$login = 'myusername';
$password   = 'myapiakey';

if($dns->auth( [ 'login' => $login, 'password' => $password ] ) ) {
  $zones = $dns->getDnsZones();
  $new_record = [ 'name' => 'subdomain',
                  'type' => 'A',
                  'data' => '1.2.3.4' ];

  $add = $dns->setDnsRecord( 'example.org', $new_record );
}
```
