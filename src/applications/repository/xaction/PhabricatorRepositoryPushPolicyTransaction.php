<?php

final class PhabricatorRepositoryPushPolicyTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:push-policy';

  public function generateOldValue($object) {
    return $object->getPushPolicy();
  }

  public function applyInternalEffects($object, $value) {
    $object->setPushPolicy($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the push policy of this repository from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldPolicy(),
      $this->renderNewPolicy());
  }

}
