<?php

final class PhabricatorUserTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Users');
  }

}
