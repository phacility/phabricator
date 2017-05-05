<?php

final class PhabricatorSlowvoteResponsesTransaction
  extends PhabricatorSlowvoteTransactionType {

  const TRANSACTIONTYPE = 'vote:responses';

  public function generateOldValue($object) {
    return (int)$object->getResponseVisibility();
  }

  public function applyInternalEffects($object, $value) {
    $object->setResponseVisibility($value);
  }

  public function getTitle() {
    // TODO: This could be more detailed
    return pht(
      '%s changed who can see the responses.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    // TODO: This could be more detailed
    return pht(
      '%s changed who can see the responses of %s.',
        $this->renderAuthor(),
        $this->renderObject());
  }

}
