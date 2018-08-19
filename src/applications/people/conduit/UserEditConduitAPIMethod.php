<?php

final class UserEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'user.edit';
  }

  public function newEditEngine() {
    return new PhabricatorUserEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to edit a user. (Users can not be created via '.
      'the API.)');
  }

}
