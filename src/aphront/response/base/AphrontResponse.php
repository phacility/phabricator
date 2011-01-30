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

/**
 * @group aphront
 */
abstract class AphrontResponse {

  private $request;
  private $cacheable = false;
  private $responseCode = 200;

  public function setRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function getHeaders() {
    return array();
  }
  
  public function setCacheDurationInSeconds($duration) {
    $this->cacheable = $duration;
    return $this;
  }
  
  public function setHTTPResponseCode($code) {
    $this->responseCode = $code;
    return $this;
  }
  
  public function getHTTPResponseCode() {
    return $this->responseCode;
  }

  public function getCacheHeaders() {
    if ($this->cacheable) {
      $epoch = time() + $this->cacheable;
      return array(
        array('Expires',       gmdate('D, d M Y H:i:s', $epoch) . ' GMT'),
      );
    } else {
      return array(
        array('Cache-Control', 'private, no-cache, no-store, must-revalidate'),
        array('Expires',       'Sat, 01 Jan 2000 00:00:00 GMT'),
      );
    }
  }

  abstract public function buildResponseString();

}
