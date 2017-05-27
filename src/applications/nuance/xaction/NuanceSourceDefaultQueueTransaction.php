<?php

final class NuanceSourceDefaultQueueTransaction
  extends NuanceSourceTransactionType {

  const TRANSACTIONTYPE = 'source.queue.default';

  public function generateOldValue($object) {
    return $object->getDefaultQueuePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDefaultQueuePHID($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the default queue for this source from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldHandle(),
      $this->renderNewHandle());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if (!$object->getDefaultQueuePHID() && !$xactions) {
      $errors[] = $this->newRequiredError(
          pht('Sources must have a default queue.'));
    }

    foreach ($xactions as $xaction) {
      if (!$xaction->getNewValue()) {
        $errors[] = $this->newRequiredError(
            pht('Sources must have a default queue.'));
      }
    }

    return $errors;
  }

}
