<?php

final class PhabricatorSlowvoteShuffleTransaction
  extends PhabricatorSlowvoteTransactionType {

  const TRANSACTIONTYPE = 'vote:shuffle';

  public function generateOldValue($object) {
    return (bool)$object->getShuffle();
  }

  public function generateNewValue($object, $value) {
    return (bool)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setShuffle((int)$value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s made poll responses appear in a random order.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s made poll responses appear in a fixed order.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s made %s responses appear in a random order.',
        $this->renderAuthor(),
        $this->renderObject());

    } else {
      return pht(
        '%s made %s responses appear in a fixed order.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    return 'fa-refresh';
  }

}
