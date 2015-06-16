<?php

final class ConduitAPIResponse extends Phobject {

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

}
