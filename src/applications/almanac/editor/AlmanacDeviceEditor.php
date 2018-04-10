<?php

final class AlmanacDeviceEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Almanac Device');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

}
