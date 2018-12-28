<?php

final class PhabricatorAuthHighSecurityRequiredException extends Exception {

  private $cancelURI;
  private $factors;
  private $factorValidationResults;
  private $isSessionUpgrade;

  public function setFactorValidationResults(array $results) {
    assert_instances_of($results, 'PhabricatorAuthFactorResult');
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

  public function setIsSessionUpgrade($is_upgrade) {
    $this->isSessionUpgrade = $is_upgrade;
    return $this;
  }

  public function getIsSessionUpgrade() {
    return $this->isSessionUpgrade;
  }

}
