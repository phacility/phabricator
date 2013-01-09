<?php

/**
 * @group conduit
 */
final class ConduitAPI_user_find_Method
  extends ConduitAPI_user_Method {

  public function getMethodDescription() {
    return "Find user PHIDs which correspond to provided user aliases. ".
           "Returns NULL for aliases which do have any corresponding PHIDs.";
  }

  public function defineParamTypes() {
    return array(
      'aliases' => 'required nonempty list<string>'
    );
  }

  public function defineReturnType() {
    return 'nonempty dict<string, phid>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $users = id(new PhabricatorUser())->loadAllWhere(
      'username in (%Ls)',
      $request->getValue('aliases'));

    return mpull($users, 'getPHID', 'getUsername');
  }

}
