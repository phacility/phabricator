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
 * @group aphront
 */
final class AphrontJSONResponse extends AphrontResponse {

  private $content;
  private $addJSONShield;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function setAddJSONShield($should_add) {
    $this->addJSONShield = $should_add;
    return $this;
  }

  public function shouldAddJSONShield() {
    if ($this->addJSONShield === null) {
      return true;
    }
    return (bool) $this->addJSONShield;
  }

  public function buildResponseString() {
    $response = $this->encodeJSONForHTTPResponse($this->content);
    if ($this->shouldAddJSONShield()) {
      $response = $this->addJSONShield($response);
    }
    return $response;
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', 'application/json'),
    );
    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }
}
