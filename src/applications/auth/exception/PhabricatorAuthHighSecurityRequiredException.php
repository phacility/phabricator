<?php

final class PhabricatorAuthHighSecurityRequiredException extends Exception {

  private $cancelURI;
  private $factors;
  private $factorValidationResults;

  public function setFactorValidationResults(array $results) {
    $this->factorValidationResults = $results;
    return $this;
  }

  public function getFactorValidationResults() {
    return $this->factorValidationResults;
  }

  public function setFactors(array $factors) {
    assert_instances_of($factors, 'PhabricatorAuthFactorConfig');
    $this->factors = $factors;
    return $this;
  }

  public function getFactors() {
    return $this->factors;
  }

  public function setCancelURI($cancel_uri) {
    $this->cancelURI = $cancel_uri;
    return $this;
  }

  public function getCancelURI() {
    return $this->cancelURI;
  }

}
