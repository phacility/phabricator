<?php

final class PhabricatorDashboardTextPanelTextTransaction
  extends PhabricatorDashboardPanelPropertyTransaction {

  const TRANSACTIONTYPE = 'text.text';

  protected function getPropertyKey() {
    return 'text';
  }

  public function newRemarkupChanges() {
    $changes = array();

    $changes[] = $this->newRemarkupChange()
      ->setOldValue($this->getOldValue())
      ->setNewValue($this->getNewValue());

    return $changes;
  }

}
