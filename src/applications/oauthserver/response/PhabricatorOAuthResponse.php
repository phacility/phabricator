<?php

/*
 * Copyright 2012 Facebook, Inc.
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

/*
* @group oauthserver
*/
final class PhabricatorOAuthResponse extends AphrontResponse {

  private $content;
  private $uri;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }
  private function getContent() {
    return $this->content;
  }

  private function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }
  private function getURI() {
    return $this->uri;
  }

  public function setMalformed($malformed) {
    if ($malformed) {
      $this->setHTTPResponseCode(400);
      $this->setContent(array('error' => $malformed));
    }
    return $this;
  }

  public function setNotFound($not_found) {
    if ($not_found) {
      $this->setHTTPResponseCode(404);
      $this->setContent(array('error' => $not_found));
    }
    return $this;
  }

  public function setRedirect(PhutilURI $uri) {
    if ($uri) {
      $this->setHTTPResponseCode(302);
      $this->setURI($uri);
      $this->setContent(null);
    }
    return $this;
  }

  public function __construct() {
    $this->setHTTPResponseCode(200);
    return $this;
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', 'application/json'),
    );
    if ($this->getURI()) {
      $headers[] = array('Location', $this->getURI());
    }
    // TODO -- T844 set headers with X-Auth-Scopes, etc
    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }

  public function buildResponseString() {
    $content = $this->getContent();
    if ($content) {
      return $this->encodeJSONForHTTPResponse($content);
    }
    return '';
  }

}
