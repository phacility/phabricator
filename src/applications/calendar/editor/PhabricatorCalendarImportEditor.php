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

}
