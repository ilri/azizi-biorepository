<?php
class Test {
   public function __construct() {
      $this->ADAuthenticate("solo.kihara", "solo183");
   }
   private function ADAuthenticate($username, $password) {
//      echo '<pre>'.print_r(Config::$config, true).'</pre>';
      $ldapConnection = ldap_connect("ilrikead01.ilri.cgiarad.org");
      if (!$ldapConnection) {
         echo "Could not connect to the AD server!";
      } else {
         $ldapConnection = ldap_connect("ilrikead01.ilri.cgiarad.org");
         if (!$ldapConnection)
            return "Could not connect to the LDAP host";
         else {
            if (ldap_bind($ldapConnection, "$username@ilri.cgiarad.org", $password)) {
               ldap_set_option($ldapConnection, LDAP_OPT_REFERRALS, 0);
               ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
               $ldapSr = ldap_search($ldapConnection, 'ou=ILRI Kenya,dc=ilri,dc=cgiarad,dc=org', "(sAMAccountName=$username)", array('sn', 'givenName', 'title'));
               if (!$ldapSr) {
                  $this->CreateLogEntry('', 'fatal');
                  return "Connected successfully to the AD server, but cannot perform the search!";
               }
               $entry1 = ldap_first_entry($ldapConnection, $ldapSr);
               if (!$entry1) {
                  return "Connected successfully to the AD server, but there was an error while searching the AD!";
               }
               $ldapAttributes = ldap_get_attributes($ldapConnection, $entry1);
               print_r($ldapAttributes["title"]);
               return 0;
            } else {
               return "There was an error while binding user '$username' to the AD server!";
            }
         }
      }
   }
}
$obj= new Test();
?>