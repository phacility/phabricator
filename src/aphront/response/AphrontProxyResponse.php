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

/**
 * Base class for responses which augment other types of responses. For example,
 * a response might be substantially an Ajax response, but add structure to the
 * response content. It can do this by extending @{class:AphrontProxyResponse},
 * instantiating an @{class:AphrontAjaxResponse} in @{method:buildProxy}, and
 * then using the proxy to construct the response string in
 * @{method:buildResponseString}.
 *
 * @group aphront
 */
abstract class AphrontProxyResponse extends AphrontResponse {

  private $proxy;

  protected function getProxy() {
    if (!$this->proxy) {
      $this->proxy = $this->buildProxy();
    }
    return $this->proxy;
  }

  public function setRequest($request) {
    $this->getProxy()->setRequest($request);
    return $this;
  }

  public function getRequest() {
    return $this->getProxy()->getRequest();
  }

  public function getHeaders() {
    return $this->getProxy()->getHeaders();
  }

  public function setCacheDurationInSeconds($duration) {
    $this->getProxy()->setCacheDurationInSeconds($duration);
    return $this;
  }

  public function setLastModified($epoch_timestamp) {
    $this->getProxy()->setLastModified($epoch_timestamp);
    return $this;
  }

  public function setHTTPResponseCode($code) {
    $this->getProxy()->setHTTPResponseCode($code);
    return $this;
  }

  public function getHTTPResponseCode() {
    return $this->getProxy()->getHTTPResponseCode();
  }

  public function setFrameable($frameable) {
    $this->getProxy()->setFrameable($frameable);
    return $this;
  }

  public function getCacheHeaders() {
    return $this->getProxy()->getCacheHeaders();
  }

  abstract protected function buildProxy();

}
