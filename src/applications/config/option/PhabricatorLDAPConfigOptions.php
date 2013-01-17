<?php

final class PhabricatorLDAPConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Integration with LDAP");
  }

  public function getDescription() {
    return pht("LDAP authentication and integration options.");
  }

  public function getOptions() {
    return array(
      $this->newOption('ldap.auth-enabled', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Enable LDAP Authentication"),
            pht("Disable LDAP Authentication"),
          ))
        ->setDescription(
          pht('Enable LDAP for authentication and registration.')),
      $this->newOption('ldap.hostname', 'string', null)
        ->setDescription(pht('LDAP server host name.')),
      $this->newOption('ldap.port', 'int', 389)
        ->setDescription(pht('LDAP server port.')),
      $this->newOption('ldap.anonymous-user-name', 'string', null)
        ->setDescription(
          pht('Username to login to LDAP server with.')),
      $this->newOption('ldap.anonymous-user-password', 'string', null)
        ->setMasked(true)
        ->setDescription(
          pht('Password to login to LDAP server with.')),

      // TODO: I have only a vague understanding of what these options do;
      // improve the documentation here and provide examples.

      $this->newOption('ldap.base_dn', 'string', null)
        ->setDescription(pht('LDAP base domain name.')),
      $this->newOption('ldap.search_attribute', 'string', null),
      $this->newOption('ldap.search-first', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Enabled"),
            pht("Disabled"),
          )),
      $this->newOption('ldap.username-attribute', 'string', null),
      $this->newOption('ldap.real_name_attributes', 'list<string>', array())
        ->setDescription(
          pht(
            "Attribute or attributes to use as the user's real name. If ".
            "multiple attributes are provided, they will be joined with ".
            "spaces.")),
      $this->newOption('ldap.activedirectory_domain', 'string', null),
      $this->newOption('ldap.version', 'int', 3),
      $this->newOption('ldap.referrals', 'bool', true)
        ->setBoolOptions(
          array(
            pht("Follow referrals"),
            pht("Do not follow referrals"),
          ))
        ->setDescription(
          pht("You may need to disable this if you use Windows 2003 ".
              "Active Directory.")),
      $this->newOption('ldap.start-tls', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Use STARTTLS"),
            pht("Do not use STARTTLS"),
          ))
        ->setDescription(pht("Should LDAP use STARTTLS?"))
    );
  }

}
