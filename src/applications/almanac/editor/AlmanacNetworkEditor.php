<?php

final class AlmanacNetworkEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Almanac Network');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this network.', $author);
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
