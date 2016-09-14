<?php

final class PhabricatorCalendarEventCancelTransaction
  extends PhabricatorCalendarEventTransactionType {

  const TRANSACTIONTYPE = 'calendar.cancel';

  public function generateOldValue($object) {
    return (int)$object->getIsCancelled();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsCancelled($value);
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s cancelled this event.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s reinstated this event.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    if ($this->getNewValue()) {
      return pht(
        '%s cancelled %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s reinstated %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

}
