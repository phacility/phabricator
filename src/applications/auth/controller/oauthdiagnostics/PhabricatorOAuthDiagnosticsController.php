<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorOAuthDiagnosticsController
  extends PhabricatorAuthController {

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

    $res_ok = '<strong style="color: #00aa00;">OK</strong>';
    $res_no = '<strong style="color: #aa0000;">NO</strong>';
    $res_na = '<strong style="color: #999999;">N/A</strong>';

    $results = array();

    if (!$auth_enabled) {
      $results['facebook.auth-enabled'] = array(
        $res_no,
        'false',
        'Facebook authentication is disabled in the configuration. Edit the '.
        'environmental configuration to enable "facebook.auth-enabled".');
    } else {
      $results['facebook.auth-enabled'] = array(
        $res_ok,
        'true',
        'Facebook authentication is enabled.');
    }

    if (!$client_id) {
      $results['facebook.application-id'] = array(
        $res_no,
        null,
        'No Facebook Application ID is configured. Edit the environmental '.
        'configuration to specify an application ID in '.
        '"facebook.application-id". To generate an ID, sign into Facebook, '.
        'install the "Developer" application, and use it to create a new '.
        'Facebook application.');
    } else {
      $results['facebook.application-id'] = array(
        $res_ok,
        $client_id,
        'Application ID is set.');
    }

    if (!$client_secret) {
      $results['facebook.application-secret'] = array(
        $res_no,
        null,
        'No Facebook Application secret is configured. Edit the environmental '.
        'configuration to specify an Application Secret, in '.
        '"facebook.application-secret". You can find the application secret '.
        'in the Facebook "Developer" application on Facebook.');
    } else {
      $results['facebook.application-secret'] = array(
        $res_ok,
        "It's a secret!",
        'Application secret is set.');
    }

    $timeout = stream_context_create(
      array(
        'http' => array(
          'ignore_errors' => true,
          'timeout'       => 5,
        ),
      ));
    $timeout_strict = stream_context_create(
      array(
        'http' => array(
          'timeout'       => 5,
        ),
      ));

    $internet = @file_get_contents("http://google.com/", false, $timeout);
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

    $facebook = @file_get_contents("http://facebook.com/", false, $timeout);
    if ($facebook === false) {
      $results['facebook.com'] = array(
        $res_no,
        null,
        'Unable to make an HTTP request to facebook.com. Facebook may be '.
        'down or inaccessible.');
    } else {
      $results['facebook.com'] = array(
        $res_ok,
        null,
        'Made a request to facebook.com.');
    }

    $graph = @file_get_contents(
      "https://graph.facebook.com/me",
      false,
      $timeout);
    if ($graph === false) {
      $results['Facebook Graph'] = array(
        $res_no,
        null,
        "Unable to make an HTTPS request to graph.facebook.com. ".
        "The Facebook graph may be down or inaccessible.");
    } else {
      $results['Facebook Graph'] = array(
        $res_ok,
        null,
        'Made a request to graph.facebook.com.');
    }

    $test_uri = new PhutilURI('https://graph.facebook.com/oauth/access_token');
    $test_uri->setQueryParams(
      array(
        'client_id'       => $client_id,
        'client_secret'   => $client_secret,
        'grant_type'      => 'client_credentials',
      ));

    $token_value  = @file_get_contents($test_uri, false, $timeout);
    $token_strict = @file_get_contents($test_uri, false, $timeout_strict);
    if ($token_value === false) {
      $results['App Login'] = array(
        $res_no,
        null,
        "Unable to perform an application login with your Application ID and ".
        "Application Secret. You may have mistyped or misconfigured them; ".
        "Facebook may have revoked your authorization; or Facebook may be ".
        "having technical problems.");
    } else {
      if ($token_strict) {
        $results['App Login'] = array(
          $res_ok,
          $token_strict,
          "Raw application login to Facebook works.");
      } else {
        $data = json_decode($token_value, true);
        if (!is_array($data)) {
          $results['App Login'] = array(
            $res_no,
            $token_value,
            "Application Login failed but the graph server did not respond ".
            "with valid JSON error information. Facebook may be experiencing ".
            "technical problems.");
        } else {
          $results['App Login'] = array(
            $res_no,
            null,
            "Application Login failed with error: ".$token_value);
        }
      }
    }

    return $this->renderResults($results);
  }

  private function renderResults($results) {

    $rows = array();
    foreach ($results as $key => $result) {
      $rows[] = array(
        phutil_escape_html($key),
        $result[0],
        phutil_escape_html($result[1]),
        phutil_escape_html($result[2]),
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

    $panel_view = new AphrontPanelView();
    $panel_view->setHeader('Facebook Auth Diagnostics');
    $panel_view->appendChild(
      '<p class="aphront-panel-instructions">These tests may be able to '.
      'help diagnose the root cause of problems you experience with '.
      'Facebook Authentication. Reload the page to run the tests again.</p>');
    $panel_view->appendChild($table_view);

    return $this->buildStandardPageResponse(
      $panel_view,
      array(
        'title' => 'Facebook Auth Diagnostics',
      ));

  }

}
