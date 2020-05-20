<?php

final class PhutilICSParserException extends Exception {

  private $parserFailureCode;

  public function setParserFailureCode($code) {
    $this->parserFailureCode = $code;
    return $this;
  }

  public function getParserFailureCode() {
    return $this->parserFailureCode;
  }

}
