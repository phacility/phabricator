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
 * @group conduit
 */
class ConduitAPIResponse {

  private $result;
  private $errorCode;
  private $errorInfo;

  public function setResult($result) {
    $this->result = $result;
    return $this;
  }
  public function getResult() {
    return $this->result;
  }

  public function setErrorCode($error_code) {
    $this->errorCode = $error_code;
    return $this;
  }
  public function getErrorCode() {
    return $this->errorCode;
  }

  public function setErrorInfo($error_info) {
    $this->errorInfo = $error_info;
    return $this;
  }
  public function getErrorInfo() {
    return $this->errorInfo;
  }

  public function toDictionary() {
    return array(
      'result'     => $this->getResult(),
      'error_code' => $this->getErrorCode(),
      'error_info' => $this->getErrorInfo(),
    );
  }

  public function toJSON() {
    return json_encode($this->toDictionary());
  }
}
