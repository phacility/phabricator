<?php

final class PhabricatorUserProfileEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('User Profiles');
  }


}
