<?php

final class PhabricatorSlowvoteCloseTransaction
  extends PhabricatorSlowvoteTransactionType {

  const TRANSACTIONTYPE = 'vote:close';

  public function generateOldValue($object) {
    return (bool)$object->getIsClosed();
  }

  public function generateNewValue($object, $value) {
    return (bool)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsClosed((int)$value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s closed this poll.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s reopened this poll.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s closed %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s reopened %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();

    if ($new) {
      return 'fa-ban';
    } else {
      return 'fa-pencil';
    }
  }

}
