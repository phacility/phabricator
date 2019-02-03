<?php

final class PhabricatorAuthFactorProviderDuoHostnameTransaction
  extends PhabricatorAuthFactorProviderTransactionType {

  const TRANSACTIONTYPE = 'duo.hostname';

  public function generateOldValue($object) {
    $key = PhabricatorDuoAuthFactor::PROP_HOSTNAME;
    return $object->getAuthFactorProviderProperty($key);
  }

  public function applyInternalEffects($object, $value) {
    $key = PhabricatorDuoAuthFactor::PROP_HOSTNAME;
    $object->setAuthFactorProviderProperty($key, $value);
  }

  public function getTitle() {
    return pht(
      '%s changed the hostname for this provider from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if (!$this->isDuoProvider($object)) {
      return $errors;
    }

    $old_value = $this->generateOldValue($object);
    if ($this->isEmptyTextTransaction($old_value, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Duo providers must have an API hostname.'));
    }

    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if (!strlen($new_value)) {
        continue;
      }

      if ($new_value === $old_value) {
        continue;
      }

      try {
        PhabricatorDuoAuthFactor::requireDuoAPIHostname($new_value);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          $ex->getMessage(),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
