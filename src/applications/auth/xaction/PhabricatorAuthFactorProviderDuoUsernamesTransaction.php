<?php

final class PhabricatorAuthFactorProviderDuoUsernamesTransaction
  extends PhabricatorAuthFactorProviderTransactionType {

  const TRANSACTIONTYPE = 'duo.usernames';

  public function generateOldValue($object) {
    $key = PhabricatorDuoAuthFactor::PROP_USERNAMES;
    return $object->getAuthFactorProviderProperty($key);
  }

  public function applyInternalEffects($object, $value) {
    $key = PhabricatorDuoAuthFactor::PROP_USERNAMES;
    $object->setAuthFactorProviderProperty($key, $value);
  }

  public function getTitle() {
    return pht(
      '%s changed the username policy for this provider from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

}
