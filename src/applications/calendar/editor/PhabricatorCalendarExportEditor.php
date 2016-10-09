<?php

final class PhabricatorCalendarExportEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Calendar Exports');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this export.', $author);
  }

}
