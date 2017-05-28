<?php

final class PhabricatorBadgesBadgeIconTransaction
  extends PhabricatorBadgesBadgeTransactionType {

  const TRANSACTIONTYPE = 'badge.icon';

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
      '%s changed the badge icon from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old),
      $this->renderValue($new));
  }

  public function getTitleForFeed() {
    $old = $this->getIconLabel($this->getOldValue());
    $new = $this->getIconLabel($this->getNewValue());

    return pht(
      '%s changed the badge icon for %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderValue($old),
      $this->renderValue($new));
  }

  private function getIconLabel($icon) {
    $set = new PhabricatorBadgesIconSet();
    return $set->getIconLabel($icon);
  }

  public function getIcon() {
    return $this->getNewValue();
  }

}
