<?php

final class PhabricatorAuthFactorProviderDuoCredentialTransaction
  extends PhabricatorAuthFactorProviderTransactionType {

  const TRANSACTIONTYPE = 'duo.credential';

  public function generateOldValue($object) {
    $key = PhabricatorDuoAuthFactor::PROP_CREDENTIAL;
    return $object->getAuthFactorProviderProperty($key);
  }

  public function applyInternalEffects($object, $value) {
    $key = PhabricatorDuoAuthFactor::PROP_CREDENTIAL;
    $object->setAuthFactorProviderProperty($key, $value);
  }

  public function getTitle() {
    return pht(
      '%s changed the credential for this provider from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldHandle(),
      $this->renderNewHandle());
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();
    $errors = array();

    if (!$this->isDuoProvider($object)) {
      return $errors;
    }

    $old_value = $this->generateOldValue($object);
    if ($this->isEmptyTextTransaction($old_value, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Duo providers must have an API credential.'));
    }

    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if (!strlen($new_value)) {
        continue;
      }

      if ($new_value === $old_value) {
        continue;
      }

      $credential = id(new PassphraseCredentialQuery())
        ->setViewer($actor)
        ->withIsDestroyed(false)
        ->withPHIDs(array($new_value))
        ->executeOne();
      if (!$credential) {
        $errors[] = $this->newInvalidError(
          pht(
            'Credential ("%s") is not valid.',
            $new_value),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
