<?php

final class AlmanacDeviceEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Almanac Device');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this device.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function supportsSearch() {
    return true;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

}
