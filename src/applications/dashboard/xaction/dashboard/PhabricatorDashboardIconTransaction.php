<?php

final class PhabricatorDashboardIconTransaction
  extends PhabricatorDashboardTransactionType {

  const TRANSACTIONTYPE = 'dashboard:icon';

  public function generateOldValue($object) {
    return $object->getIcon();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIcon($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    return pht(
      '%s changed the icon for this dashboard from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

}
