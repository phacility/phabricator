<?php

final class PhabricatorCalendarEventIconTransaction
  extends PhabricatorCalendarEventTransactionType {

  const TRANSACTIONTYPE = 'calendar.icon';

  public function generateOldValue($object) {
    return $object->getIcon();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIcon($value);
  }

  public function shouldHide() {
    if ($this->isCreateTransaction()) {
      return true;
    }

    return false;
  }

  public function getTitle() {
    $old = $this->getIconLabel($this->getOldValue());
    $new = $this->getIconLabel($this->getNewValue());

    return pht(
      '%s changed the event icon from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old),
      $this->renderValue($new));
  }

  public function getTitleForFeed() {
    $old = $this->getIconLabel($this->getOldValue());
    $new = $this->getIconLabel($this->getNewValue());

    return pht(
      '%s changed the icon for %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderValue($old),
      $this->renderValue($new));
  }

  private function getIconLabel($icon) {
    $set = new PhabricatorCalendarIconSet();
    return $set->getIconLabel($icon);
  }

}
