<?php

final class PhabricatorAuthFactorProviderDuoEnrollTransaction
  extends PhabricatorAuthFactorProviderTransactionType {

  const TRANSACTIONTYPE = 'duo.enroll';

  public function generateOldValue($object) {
    $key = PhabricatorDuoAuthFactor::PROP_ENROLL;
    return $object->getAuthFactorProviderProperty($key);
  }

  public function applyInternalEffects($object, $value) {
    $key = PhabricatorDuoAuthFactor::PROP_ENROLL;
    $object->setAuthFactorProviderProperty($key, $value);
  }

  public function getTitle() {
    return pht(
      '%s changed the enrollment policy for this provider from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

}
