<?php

/**
 * Implements core OAuth 2.0 Server logic.
 *
 * This class should be used behind business logic that parses input to
 * determine pertinent @{class:PhabricatorUser} $user,
 * @{class:PhabricatorOAuthServerClient} $client(s),
 * @{class:PhabricatorOAuthServerAuthorizationCode} $code(s), and.
 * @{class:PhabricatorOAuthServerAccessToken} $token(s).
 *
 * For an OAuth 2.0 server, there are two main steps:
 *
 * 1) Authorization - the user authorizes a given client to access the data
 * the OAuth 2.0 server protects. Once this is achieved / if it has
 * been achived already, the OAuth server sends the client an authorization
 * code.
 * 2) Access Token - the client should send the authorization code received in
 * step 1 along with its id and secret to the OAuth server to receive an
 * access token. This access token can later be used to access Phabricator
 * data on behalf of the user.
 *
 * @task auth Authorizing @{class:PhabricatorOAuthServerClient}s and
 *            generating @{class:PhabricatorOAuthServerAuthorizationCode}s
 * @task token Validating @{class:PhabricatorOAuthServerAuthorizationCode}s
 *             and generating @{class:PhabricatorOAuthServerAccessToken}s
 * @task internal Internals
 */
final class PhabricatorOAuthServer extends Phobject {

  const AUTHORIZATION_CODE_TIMEOUT = 300;

  private $user;
  private $client;

  private function getUser() {
    if (!$this->user) {
      throw new PhutilInvalidStateException('setUser');
    }
    return $this->user;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  private function getClient() {
    if (!$this->client) {
      throw new PhutilInvalidStateException('setClient');
    }
    return $this->client;
  }

  public function setClient(PhabricatorOAuthServerClient $client) {
    $this->client = $client;
    return $this;
  }

  /**
   * @task auth
   * @return tuple <bool hasAuthorized, ClientAuthorization or null>
   */
  public function userHasAuthorizedClient(array $scope) {

    $authorization = id(new PhabricatorOAuthClientAuthorization())
      ->loadOneWhere(
        'userPHID = %s AND clientPHID = %s',
        $this->getUser()->getPHID(),
        $this->getClient()->getPHID());
    if (empty($authorization)) {
      return array(false, null);
    }

    if ($scope) {
      $missing_scope = array_diff_key($scope, $authorization->getScope());
    } else {
      $missing_scope = false;
    }

    if ($missing_scope) {
      return array(false, $authorization);
    }

    return array(true, $authorization);
  }

  /**
   * @task auth
   */
  public function authorizeClient(array $scope) {
    $authorization = new PhabricatorOAuthClientAuthorization();
    $authorization->setUserPHID($this->getUser()->getPHID());
    $authorization->setClientPHID($this->getClient()->getPHID());
    $authorization->setScope($scope);
    $authorization->save();

    return $authorization;
  }

  /**
   * @task auth
   */
  public function generateAuthorizationCode(PhutilURI $redirect_uri) {

    $code   = Filesystem::readRandomCharacters(32);
    $client = $this->getClient();

    $authorization_code = new PhabricatorOAuthServerAuthorizationCode();
    $authorization_code->setCode($code);
    $authorization_code->setClientPHID($client->getPHID());
    $authorization_code->setClientSecret($client->getSecret());
    $authorization_code->setUserPHID($this->getUser()->getPHID());
    $authorization_code->setRedirectURI((string)$redirect_uri);
    $authorization_code->save();

    return $authorization_code;
  }

  /**
   * @task token
   */
  public function generateAccessToken() {

    $token = Filesystem::readRandomCharacters(32);

    $access_token = new PhabricatorOAuthServerAccessToken();
    $access_token->setToken($token);
    $access_token->setUserPHID($this->getUser()->getPHID());
    $access_token->setClientPHID($this->getClient()->getPHID());
    $access_token->save();

    return $access_token;
  }

  /**
   * @task token
   */
  public function validateAuthorizationCode(
    PhabricatorOAuthServerAuthorizationCode $test_code,
    PhabricatorOAuthServerAuthorizationCode $valid_code) {

    // check that all the meta data matches
    if ($test_code->getClientPHID() != $valid_code->getClientPHID()) {
      return false;
    }
    if ($test_code->getClientSecret() != $valid_code->getClientSecret()) {
      return false;
    }

    // check that the authorization code hasn't timed out
    $created_time = $test_code->getDateCreated();
    $must_be_used_by = $created_time + self::AUTHORIZATION_CODE_TIMEOUT;
    return (time() < $must_be_used_by);
  }

  /**
   * @task token
   */
  public function authorizeToken(
    PhabricatorOAuthServerAccessToken $token) {

    $user_phid = $token->getUserPHID();
    $client_phid = $token->getClientPHID();

    $authorization = id(new PhabricatorOAuthClientAuthorizationQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withUserPHIDs(array($user_phid))
      ->withClientPHIDs(array($client_phid))
      ->executeOne();
    if (!$authorization) {
      return null;
    }

    $application = $authorization->getClient();
    if ($application->getIsDisabled()) {
      return null;
    }

    return $authorization;
  }

  public function validateRedirectURI($uri) {
    try {
      $this->assertValidRedirectURI($uri);
      return true;
    } catch (Exception $ex) {
      return false;
    }
  }

  /**
   * See http://tools.ietf.org/html/draft-ietf-oauth-v2-23#section-3.1.2
   * for details on what makes a given redirect URI "valid".
   */
  public function assertValidRedirectURI($raw_uri) {
    // This covers basics like reasonable formatting and the existence of a
    // protocol.
    PhabricatorEnv::requireValidRemoteURIForLink($raw_uri);

    $uri = new PhutilURI($raw_uri);

    $fragment = $uri->getFragment();
    if (strlen($fragment)) {
      throw new Exception(
        pht(
          'OAuth application redirect URIs must not contain URI '.
          'fragments, but the URI "%s" has a fragment ("%s").',
          $raw_uri,
          $fragment));
    }

    $protocol = $uri->getProtocol();
    switch ($protocol) {
      case 'http':
      case 'https':
        break;
      default:
        throw new Exception(
          pht(
            'OAuth application redirect URIs must only use the "http" or '.
            '"https" protocols, but the URI "%s" uses the "%s" protocol.',
            $raw_uri,
            $protocol));
    }
  }

  /**
   * If there's a URI specified in an OAuth request, it must be validated in
   * its own right. Further, it must have the same domain, the same path, the
   * same port, and (at least) the same query parameters as the primary URI.
   */
  public function validateSecondaryRedirectURI(
    PhutilURI $secondary_uri,
    PhutilURI $primary_uri) {

    // The secondary URI must be valid.
    if (!$this->validateRedirectURI($secondary_uri)) {
      return false;
    }

    // Both URIs must point at the same domain.
    if ($secondary_uri->getDomain() != $primary_uri->getDomain()) {
      return false;
    }

    // Both URIs must have the same path
    if ($secondary_uri->getPath() != $primary_uri->getPath()) {
      return false;
    }

    // Both URIs must have the same port
    if ($secondary_uri->getPort() != $primary_uri->getPort()) {
      return false;
    }

    // Any query parameters present in the first URI must be exactly present
    // in the second URI.
    $need_params = $primary_uri->getQueryParams();
    $have_params = $secondary_uri->getQueryParams();

    foreach ($need_params as $key => $value) {
      if (!array_key_exists($key, $have_params)) {
        return false;
      }
      if ((string)$have_params[$key] != (string)$value) {
        return false;
      }
    }

    // If the first URI is HTTPS, the second URI must also be HTTPS. This
    // defuses an attack where a third party with control over the network
    // tricks you into using HTTP to authenticate over a link which is supposed
    // to be HTTPS only and sniffs all your token cookies.
    if (strtolower($primary_uri->getProtocol()) == 'https') {
      if (strtolower($secondary_uri->getProtocol()) != 'https') {
        return false;
      }
    }

    return true;
  }

}
