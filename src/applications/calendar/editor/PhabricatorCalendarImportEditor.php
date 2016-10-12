<?php

final class PhabricatorCalendarImportEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Calendar Imports');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this import.', $author);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if ($this->getIsNewObject()) {
      $actor = $this->getActor();

      $import_engine = $object->getEngine();
      $import_engine->didCreateImport($actor, $object);
    }

    return $xactions;
  }


}
