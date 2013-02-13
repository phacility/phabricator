<?php

final class PhabricatorOAuthDiagnosticsController
  extends PhabricatorAuthController {

  private $provider;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->provider = PhabricatorOAuthProvider::newProvider($data['provider']);
  }

  public function processRequest() {

    $provider = $this->provider;

    $auth_enabled   = $provider->isProviderEnabled();
    $client_id      = $provider->getClientID();
    $client_secret  = $provider->getClientSecret();
    $key            = $provider->getProviderKey();
    $name           = $provider->getProviderName();

    $res_ok = hsprintf('<strong style="color: #00aa00;">OK</strong>');
    $res_no = hsprintf('<strong style="color: #aa0000;">NO</strong>');
    $res_na = hsprintf('<strong style="color: #999999;">N/A</strong>');

    $results = array();
    $auth_key = $key . '.auth-enabled';
    if (!$auth_enabled) {
      $results[$auth_key] = array(
        $res_no,
        'false',
        $name . ' authentication is disabled in the configuration. Edit the '.
        'Phabricator configuration to enable "'.$auth_key.'".');
    } else {
      $results[$auth_key] = array(
        $res_ok,
        'true',
        $name.' authentication is enabled.');
    }

    $client_id_key = $key. '.application-id';
    if (!$client_id) {
      $results[$client_id_key] = array(
        $res_no,
        null,
        'No '.$name.' Application ID is configured. Edit the Phabricator '.
        'configuration to specify an application ID in '.
        '"'.$client_id_key.'". '.$provider->renderGetClientIDHelp());
    } else {
      $results[$client_id_key] = array(
        $res_ok,
        $client_id,
        'Application ID is set.');
    }

    $client_secret_key = $key.'.application-secret';
    if (!$client_secret) {
      $results[$client_secret_key] = array(
        $res_no,
        null,
        'No '.$name.' Application secret is configured. Edit the '.
        'Phabricator configuration to specify an Application Secret, in '.
        '"'.$client_secret_key.'". '.$provider->renderGetClientSecretHelp());
    } else {
      $results[$client_secret_key] = array(
        $res_ok,
        "It's a secret!",
        'Application secret is set.');
    }

    $timeout = 5;

    $internet = HTTPSFuture::loadContent("http://google.com/", $timeout);
    if ($internet === false) {
      $results['internet'] = array(
        $res_no,
        null,
        'Unable to make an HTTP request to Google. Check your outbound '.
        'internet connection and firewall/filtering settings.');
    } else {
      $results['internet'] = array(
        $res_ok,
        null,
        'Internet seems OK.');
    }

    $test_uris = $provider->getTestURIs();
    foreach ($test_uris as $uri) {
      $success = HTTPSFuture::loadContent($uri, $timeout);
      if ($success === false) {
        $results[$uri] = array(
          $res_no,
          null,
          "Unable to make an HTTP request to {$uri}. {$name} may be ".
          'down or inaccessible.');
      } else {
        $results[$uri] = array(
          $res_ok,
          null,
          'Made a request to '.$uri.'.');
      }
    }

    if ($provider->shouldDiagnoseAppLogin()) {
      $test_uri = new PhutilURI($provider->getTokenURI());
      $test_uri->setQueryParams(
        array(
          'client_id'       => $client_id,
          'client_secret'   => $client_secret,
          'grant_type'      => 'client_credentials',
        ));

      $future = new HTTPSFuture($test_uri);
      $future->setTimeout($timeout);
      try {
        list($body) = $future->resolvex();
        $results['App Login'] = array(
          $res_ok,
          '(A Valid Token)',
          "Raw application login to {$name} works.");
      } catch (Exception $ex) {
        if ($ex instanceof HTTPFutureResponseStatusCURL) {
          $results['App Login'] = array(
            $res_no,
            null,
            "Unable to perform an application login with your Application ID ".
            "and Application Secret. You may have mistyped or misconfigured ".
            "them; {$name} may have revoked your authorization; or {$name} ".
            "may be having technical problems.");
        } else {
          $data = json_decode($token_value, true);
          if (!is_array($data)) {
            $results['App Login'] = array(
              $res_no,
              $token_value,
              "Application Login failed but the provider did not respond ".
              "with valid JSON error information. {$name} may be experiencing ".
              "technical problems.");
          } else {
            $results['App Login'] = array(
              $res_no,
              null,
              "Application Login failed with error: ".$token_value);
          }
        }
      }
    }

    return $this->renderResults($results);
  }

  private function renderResults($results) {
    $provider = $this->provider;

    $rows = array();
    foreach ($results as $key => $result) {
      $rows[] = array(
        $key,
        $result[0],
        $result[1],
        $result[2],
      );
    }

    $table_view = new AphrontTableView($rows);
    $table_view->setHeaders(
      array(
        'Test',
        'Result',
        'Value',
        'Details',
      ));
    $table_view->setColumnClasses(
      array(
        null,
        null,
        null,
        'wide',
      ));

    $title = $provider->getProviderName() . ' Auth Diagnostics';

    $panel_view = new AphrontPanelView();
    $panel_view->setHeader($title);
    $panel_view->appendChild(hsprintf(
      '<p class="aphront-panel-instructions">These tests may be able to '.
      'help diagnose the root cause of problems you experience with %s '.
      'Authentication. Reload the page to run the tests again.</p>',
      $provider->getProviderName()));
    $panel_view->appendChild($table_view);

    return $this->buildStandardPageResponse(
      $panel_view,
      array(
        'title' => $title,
      ));
  }

}
