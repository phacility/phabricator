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
 * Isolated HTTP sink for testing.
 *
 * @group aphront
 */
final class AphrontIsolatedHTTPSink extends AphrontHTTPSink {

  private $status;
  private $headers;
  private $data;

  protected function emitHTTPStatus($code) {
    $this->status = $code;
  }

  protected function emitHeader($name, $value) {
    $this->headers[] = array($name, $value);
  }

  protected function emitData($data) {
    $this->data .= $data;
  }

  public function getEmittedHTTPStatus() {
    return $this->status;
  }

  public function getEmittedHeaders() {
    return $this->headers;
  }

  public function getEmittedData() {
    return $this->data;
  }

}
