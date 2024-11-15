<?php

final class PhabricatorRoleIconTransaction
  extends PhabricatorRoleTransactionType {

  const TRANSACTIONTYPE = 'role:icon';

  public function generateOldValue($object) {
    return $object->getIcon();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIcon($value);
  }

  public function getTitle() {
    $set = new PhabricatorRoleIconSet();
    $new = $this->getNewValue();

    return pht(
      "%s set this role's icon to %s.",
      $this->renderAuthor(),
      $this->renderValue($set->getIconLabel($new)));
  }

  public function getTitleForFeed() {
    $set = new PhabricatorRoleIconSet();
    $new = $this->getNewValue();

    return pht(
      '%s set the icon for %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderValue($set->getIconLabel($new)));
  }

  public function getIcon() {
    $new = $this->getNewValue();
    return PhabricatorRoleIconSet::getIconIcon($new);
  }

}
