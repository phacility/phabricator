<?php

final class PhabricatorRepositoryTrackOnlyTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:track-only';

  public function generateOldValue($object) {
    return $object->getTrackOnlyRules();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTrackOnlyRules($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!$new) {
      return pht(
        '%s set this repository to track all branches.',
        $this->renderAuthor());
    } else if (!$old) {
      return pht(
        '%s set this repository to track branches: %s.',
        $this->renderAuthor(),
        $this->renderValue(implode(', ', $new)));
    } else {
      return pht(
        '%s changed tracked branches from %s to %s.',
        $this->renderAuthor(),
        $this->renderValue(implode(', ', $old)),
        $this->renderValue(implode(', ', $new)));
    }
  }

  public function validateTransactions($object, array $xactions) {
    return $this->validateRefList($object, $xactions);
  }

}
