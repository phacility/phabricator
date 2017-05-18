<?php

final class PhabricatorSpacesNamespaceEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return pht('PhabricatorSpacesApplication');
  }

  public function getEditorObjectsDescription() {
    return pht('Spaces');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this space.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created space %s.', $author, $object);
  }

}
