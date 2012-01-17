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
abstract class AphrontResponse {

  private $request;
  private $cacheable = false;
  private $responseCode = 200;
  private $lastModified = null;

  protected $frameable;

  public function setRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function getHeaders() {
    $headers = array();
    if (!$this->frameable) {
      $headers[] = array('X-Frame-Options', 'Deny');
    }

    return $headers;
  }

  public function setCacheDurationInSeconds($duration) {
    $this->cacheable = $duration;
    return $this;
  }

  public function setLastModified($epoch_timestamp) {
    $this->lastModified = $epoch_timestamp;
    return $this;
  }

  public function setHTTPResponseCode($code) {
    $this->responseCode = $code;
    return $this;
  }

  public function getHTTPResponseCode() {
    return $this->responseCode;
  }

  public function setFrameable($frameable) {
    $this->frameable = $frameable;
    return $this;
  }

  public function getCacheHeaders() {
    $headers = array();
    if ($this->cacheable) {
      $headers[] = array(
        'Expires',
        $this->formatEpochTimestampForHTTPHeader(time() + $this->cacheable));
    } else {
      $headers[] = array(
        'Cache-Control',
        'private, no-cache, no-store, must-revalidate');
      $headers[] = array(
        'Expires',
        'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    if ($this->lastModified) {
      $headers[] = array(
        'Last-Modified',
        $this->formatEpochTimestampForHTTPHeader($this->lastModified));
    }

    // IE has a feature where it may override an explicit Content-Type
    // declaration by inferring a content type. This can be a security risk
    // and we always explicitly transmit the correct Content-Type header, so
    // prevent IE from using inferred content types.
    $headers[] = array('X-Content-Type-Options', 'nosniff');

    return $headers;
  }

  private function formatEpochTimestampForHTTPHeader($epoch_timestamp) {
    return gmdate('D, d M Y H:i:s', $epoch_timestamp).' GMT';
  }

  abstract public function buildResponseString();

}
