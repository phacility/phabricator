<?php

abstract class PhabricatorPackagesEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPasteApplication';
  }

}
