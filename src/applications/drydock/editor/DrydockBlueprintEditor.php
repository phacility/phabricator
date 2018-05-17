<?php

final class DrydockBlueprintEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorDrydockApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Drydock Blueprints');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this blueprint.', $author);
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
