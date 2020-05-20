<?php

final class FerretConfigurableSearchFunction
  extends FerretSearchFunction {

  private $ferretFunctionName;
  private $ferretFieldKey;

  public function supportsObject(PhabricatorFerretInterface $object) {
    return true;
  }

  public function setFerretFunctionName($ferret_function_name) {
    $this->ferretFunctionName = $ferret_function_name;
    return $this;
  }

  public function getFerretFunctionName() {
    return $this->ferretFunctionName;
  }

  public function setFerretFieldKey($ferret_field_key) {
    $this->ferretFieldKey = $ferret_field_key;
    return $this;
  }

  public function getFerretFieldKey() {
    return $this->ferretFieldKey;
  }

}
