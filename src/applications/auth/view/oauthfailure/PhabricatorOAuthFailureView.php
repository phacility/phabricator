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

class PhabricatorOAuthFailureView extends AphrontView {

  private $request;
  private $provider;

  public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  public function setOAuthProvider($provider) {
    $this->provider = $provider;
    return $this;
  }

  public function render() {
    $request = $this->request;
    $provider = $this->provider;
    $provider_name = $provider->getProviderName();

    $diagnose = null;

    $view = new AphrontRequestFailureView();
    $view->setHeader($provider_name.' Auth Failed');
    if ($this->request) {
      $view->appendChild(
        '<p>'.
          '<strong>Description:</strong> '.
          phutil_escape_html($request->getStr('error_description')).
        '</p>');
      $view->appendChild(
        '<p>'.
          '<strong>Error:</strong> '.
          phutil_escape_html($request->getStr('error')).
        '</p>');
      $view->appendChild(
        '<p>'.
          '<strong>Error Reason:</strong> '.
          phutil_escape_html($request->getStr('error_reason')).
        '</p>');
    } else {
      // TODO: We can probably refine this.
      $view->appendChild(
        '<p>Unable to authenticate with '.$provider_name.'. '.
        'There are several reasons this might happen:</p>'.
          '<ul>'.
            '<li>Phabricator may be configured with the wrong Application '.
            'Secret; or</li>'.
            '<li>the '.$provider_name.' OAuth access token may have expired; '.
            'or</li>'.
            '<li>'.$provider_name.' may have revoked authorization for the '.
            'Application; or</li>'.
            '<li>'.$provider_name.' may be having technical problems.</li>'.
          '</ul>'.
        '<p>You can try again, or login using another method.</p>');

      $provider_key = $provider->getProviderKey();
      $diagnose =
        '<a href="/oauth/'.$provider_key.'/diagnose/" class="button green">'.
          'Diagnose '.$provider_name.' OAuth Problems'.
        '</a>';
    }

    $view->appendChild(
      '<div class="aphront-failure-continue">'.
        $diagnose.
        '<a href="/login/" class="button">Continue</a>'.
      '</div>');

    return $view->render();
  }

}
