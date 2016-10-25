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

    $type_reload = PhabricatorCalendarImportReloadTransaction::TRANSACTIONTYPE;

    // We import events when you create a source, or if you later reload it
    // explicitly.
    $should_reload = $this->getIsNewObject();
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_reload) {
        $should_reload = true;
        break;
      }
    }

    if ($should_reload) {
      $actor = $this->getActor();

      $import_engine = $object->getEngine();
      $import_engine->importEventsFromSource($actor, $object);
    }

    return $xactions;
  }


}
