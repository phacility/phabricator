<?php

/**
 * Retrieve identify information from LDAP accounts.
 */
final class PhutilLDAPAuthAdapter extends PhutilAuthAdapter {

  private $hostname;
  private $port = 389;

  private $baseDistinguishedName;
  private $searchAttributes = array();
  private $usernameAttribute;
  private $realNameAttributes = array();
  private $ldapVersion = 3;
  private $ldapReferrals;
  private $ldapStartTLS;
  private $anonymousUsername;
  private $anonymousPassword;
  private $activeDirectoryDomain;
  private $alwaysSearch;

  private $loginUsername;
  private $loginPassword;

  private $ldapUserData;
  private $ldapConnection;

  public function getAdapterType() {
    return 'ldap';
  }

  public function setHostname($host) {
    $this->hostname = $host;
    return $this;
  }

  public function setPort($port) {
    $this->port = $port;
    return $this;
  }

  public function getAdapterDomain() {
    return 'self';
  }

  public function setBaseDistinguishedName($base_distinguished_name) {
    $this->baseDistinguishedName = $base_distinguished_name;
    return $this;
  }

  public function setSearchAttributes(array $search_attributes) {
    $this->searchAttributes = $search_attributes;
    return $this;
  }

  public function setUsernameAttribute($username_attribute) {
    $this->usernameAttribute = $username_attribute;
    return $this;
  }

  public function setRealNameAttributes(array $attributes) {
    $this->realNameAttributes = $attributes;
    return $this;
  }

  public function setLDAPVersion($ldap_version) {
    $this->ldapVersion = $ldap_version;
    return $this;
  }

  public function setLDAPReferrals($ldap_referrals) {
    $this->ldapReferrals = $ldap_referrals;
    return $this;
  }

  public function setLDAPStartTLS($ldap_start_tls) {
    $this->ldapStartTLS = $ldap_start_tls;
    return $this;
  }

  public function setAnonymousUsername($anonymous_username) {
    $this->anonymousUsername = $anonymous_username;
    return $this;
  }

  public function setAnonymousPassword(
    PhutilOpaqueEnvelope $anonymous_password) {
    $this->anonymousPassword = $anonymous_password;
    return $this;
  }

  public function setLoginUsername($login_username) {
    $this->loginUsername = $login_username;
    return $this;
  }

  public function setLoginPassword(PhutilOpaqueEnvelope $login_password) {
    $this->loginPassword = $login_password;
    return $this;
  }

  public function setActiveDirectoryDomain($domain) {
    $this->activeDirectoryDomain = $domain;
    return $this;
  }

  public function setAlwaysSearch($always_search) {
    $this->alwaysSearch = $always_search;
    return $this;
  }

  public function getAccountID() {
    return $this->readLDAPRecordAccountID($this->getLDAPUserData());
  }

  public function getAccountName() {
    return $this->readLDAPRecordAccountName($this->getLDAPUserData());
  }

  public function getAccountRealName() {
    return $this->readLDAPRecordRealName($this->getLDAPUserData());
  }

  public function getAccountEmail() {
    return $this->readLDAPRecordEmail($this->getLDAPUserData());
  }

  public function readLDAPRecordAccountID(array $record) {
    $key = $this->usernameAttribute;
    if (!strlen($key)) {
      $key = head($this->searchAttributes);
    }
    return $this->readLDAPData($record, $key);
  }

  public function readLDAPRecordAccountName(array $record) {
    return $this->readLDAPRecordAccountID($record);
  }

  public function readLDAPRecordRealName(array $record) {
    $parts = array();
    foreach ($this->realNameAttributes as $attribute) {
      $parts[] = $this->readLDAPData($record, $attribute);
    }
    $parts = array_filter($parts);

    if ($parts) {
      return implode(' ', $parts);
    }

    return null;
  }

  public function readLDAPRecordEmail(array $record) {
    return $this->readLDAPData($record, 'mail');
  }

  private function getLDAPUserData() {
    if ($this->ldapUserData === null) {
      $this->ldapUserData = $this->loadLDAPUserData();
    }

    return $this->ldapUserData;
  }

  private function readLDAPData(array $data, $key, $default = null) {
    $list = idx($data, $key);
    if ($list === null) {
      // At least in some cases (and maybe in all cases) the results from
      // ldap_search() are keyed in lowercase. If we missed on the first
      // try, retry with a lowercase key.
      $list = idx($data, phutil_utf8_strtolower($key));
    }

    // NOTE: In most cases, the property is an array, like:
    //
    //   array(
    //     'count' => 1,
    //     0 => 'actual-value-we-want',
    //   )
    //
    // However, in at least the case of 'dn', the property is a bare string.

    if (is_scalar($list) && strlen($list)) {
      return $list;
    } else if (is_array($list)) {
      return $list[0];
    } else {
      return $default;
    }
  }

  private function formatLDAPAttributeSearch($attribute, $login_user) {
    // If the attribute contains the literal token "${login}", treat it as a
    // query and substitute the user's login name for the token.

    if (strpos($attribute, '${login}') !== false) {
      $escaped_user = ldap_sprintf('%S', $login_user);
      $attribute = str_replace('${login}', $escaped_user, $attribute);
      return $attribute;
    }

    // Otherwise, treat it as a simple attribute search.

    return ldap_sprintf(
      '%Q=%S',
      $attribute,
      $login_user);
  }

  private function loadLDAPUserData() {
    $conn = $this->establishConnection();

    $login_user = $this->loginUsername;
    $login_pass = $this->loginPassword;

    if ($this->shouldBindWithoutIdentity()) {
      $distinguished_name = null;
      $search_query = null;
      foreach ($this->searchAttributes as $attribute) {
        $search_query = $this->formatLDAPAttributeSearch(
          $attribute,
          $login_user);
        $record = $this->searchLDAPForRecord($search_query);
        if ($record) {
          $distinguished_name = $this->readLDAPData($record, 'dn');
          break;
        }
      }
      if ($distinguished_name === null) {
        throw new PhutilAuthCredentialException();
      }
    } else {
      $search_query = $this->formatLDAPAttributeSearch(
        head($this->searchAttributes),
        $login_user);
      if ($this->activeDirectoryDomain) {
        $distinguished_name = ldap_sprintf(
          '%s@%Q',
          $login_user,
          $this->activeDirectoryDomain);
      } else {
        $distinguished_name = ldap_sprintf(
          '%Q,%Q',
          $search_query,
          $this->baseDistinguishedName);
      }
    }

    $this->bindLDAP($conn, $distinguished_name, $login_pass);

    $result = $this->searchLDAPForRecord($search_query);
    if (!$result) {
      // This is unusual (since the bind succeeded) but we've seen it at least
      // once in the wild, where the anonymous user is allowed to search but
      // the credentialed user is not.

      // If we don't have anonymous credentials, raise an explicit exception
      // here since we'll fail a typehint if we don't return an array anyway
      // and this is a more useful error.

      // If we do have anonymous credentials, we'll rebind and try the search
      // again below. Doing this automatically means things work correctly more
      // often without requiring additional configuration.
      if (!$this->shouldBindWithoutIdentity()) {
        // No anonymous credentials, so we just fail here.
        throw new Exception(
          pht(
            'LDAP: Failed to retrieve record for user "%s" when searching. '.
            'Credentialed users may not be able to search your LDAP server. '.
            'Try configuring anonymous credentials or fully anonymous binds.',
            $login_user));
      } else {
        // Rebind as anonymous and try the search again.
        $user = $this->anonymousUsername;
        $pass = $this->anonymousPassword;
        $this->bindLDAP($conn, $user, $pass);

        $result = $this->searchLDAPForRecord($search_query);
        if (!$result) {
          throw new Exception(
            pht(
              'LDAP: Failed to retrieve record for user "%s" when searching '.
              'with both user and anonymous credentials.',
              $login_user));
        }
      }
    }

    return $result;
  }

  private function establishConnection() {
    if (!$this->ldapConnection) {
      $host = $this->hostname;
      $port = $this->port;

      $profiler = PhutilServiceProfiler::getInstance();
      $call_id = $profiler->beginServiceCall(
        array(
          'type' => 'ldap',
          'call' => 'connect',
          'host' => $host,
          'port' => $this->port,
        ));

      $conn = @ldap_connect($host, $this->port);

      $profiler->endServiceCall(
        $call_id,
        array(
          'ok' => (bool)$conn,
        ));

      if (!$conn) {
        throw new Exception(
          pht('Unable to connect to LDAP server (%s:%d).', $host, $port));
      }

      $options = array(
        LDAP_OPT_PROTOCOL_VERSION => (int)$this->ldapVersion,
        LDAP_OPT_REFERRALS        => (int)$this->ldapReferrals,
      );

      foreach ($options as $name => $value) {
        $ok = @ldap_set_option($conn, $name, $value);
        if (!$ok) {
          $this->raiseConnectionException(
            $conn,
            pht(
              "Unable to set LDAP option '%s' to value '%s'!",
              $name,
              $value));
        }
      }

      if ($this->ldapStartTLS) {
        $profiler = PhutilServiceProfiler::getInstance();
        $call_id = $profiler->beginServiceCall(
          array(
            'type' => 'ldap',
            'call' => 'start-tls',
          ));

        // NOTE: This boils down to a function call to ldap_start_tls_s() in
        // C, which is a service call.
        $ok = @ldap_start_tls($conn);

        $profiler->endServiceCall(
          $call_id,
          array());

        if (!$ok) {
          $this->raiseConnectionException(
            $conn,
            pht('Unable to start TLS connection when connecting to LDAP.'));
        }
      }

      if ($this->shouldBindWithoutIdentity()) {
        $user = $this->anonymousUsername;
        $pass = $this->anonymousPassword;
        $this->bindLDAP($conn, $user, $pass);
      }

      $this->ldapConnection = $conn;
    }

    return $this->ldapConnection;
  }


  private function searchLDAPForRecord($dn) {
    $conn = $this->establishConnection();

    $results = $this->searchLDAP('%Q', $dn);

    if (!$results) {
      return null;
    }

    if (count($results) > 1) {
      throw new Exception(
        pht(
          'LDAP record query returned more than one result. The query must '.
          'uniquely identify a record.'));
    }

    return head($results);
  }

  public function searchLDAP($pattern /* ... */) {
    $args = func_get_args();
    $query = call_user_func_array('ldap_sprintf', $args);

    $conn = $this->establishConnection();

    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type'  => 'ldap',
        'call'  => 'search',
        'dn'    => $this->baseDistinguishedName,
        'query' => $query,
      ));

    $result = @ldap_search($conn, $this->baseDistinguishedName, $query);

    $profiler->endServiceCall($call_id, array());

    if (!$result) {
      $this->raiseConnectionException(
        $conn,
        pht('LDAP search failed.'));
    }

    $entries = @ldap_get_entries($conn, $result);

    if (!$entries) {
      $this->raiseConnectionException(
        $conn,
        pht('Failed to get LDAP entries from search result.'));
    }

    $results = array();
    for ($ii = 0; $ii < $entries['count']; $ii++) {
      $results[] = $entries[$ii];
    }

    return $results;
  }

  private function raiseConnectionException($conn, $message) {
    $errno = @ldap_errno($conn);
    $error = @ldap_error($conn);

    // This is `LDAP_INVALID_CREDENTIALS`.
    if ($errno == 49) {
      throw new PhutilAuthCredentialException();
    }

    if ($errno || $error) {
      $full_message = pht(
        "LDAP Exception: %s\nLDAP Error #%d: %s",
        $message,
        $errno,
        $error);
    } else {
      $full_message = pht(
        'LDAP Exception: %s',
        $message);
    }

    throw new Exception($full_message);
  }

  private function bindLDAP($conn, $user, PhutilOpaqueEnvelope $pass) {
    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type' => 'ldap',
        'call' => 'bind',
        'user' => $user,
      ));

    // NOTE: ldap_bind() dumps cleartext passwords into logs by default. Keep
    // it quiet.
    if (strlen($user)) {
      $ok = @ldap_bind($conn, $user, $pass->openEnvelope());
    } else {
      $ok = @ldap_bind($conn);
    }

    $profiler->endServiceCall($call_id, array());

    if (!$ok) {
      if (strlen($user)) {
        $this->raiseConnectionException(
          $conn,
          pht('Failed to bind to LDAP server (as user "%s").', $user));
      } else {
        $this->raiseConnectionException(
          $conn,
          pht('Failed to bind to LDAP server (without username).'));
      }
    }
  }


  /**
   * Determine if this adapter should attempt to bind to the LDAP server
   * without a user identity.
   *
   * Generally, we can bind directly if we have a username/password, or if the
   * "Always Search" flag is set, indicating that the empty username and
   * password are sufficient.
   *
   * @return bool True if the adapter should perform binds without identity.
   */
  private function shouldBindWithoutIdentity() {
    return $this->alwaysSearch || strlen($this->anonymousUsername);
  }

}
