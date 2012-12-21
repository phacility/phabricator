<?php

/**
 * @group conduit
 */
final class ConduitAPI_user_info_Method
  extends ConduitAPI_user_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return "Replaced by 'user.query'.";
  }

  public function getMethodDescription() {
    return "Retrieve information about a user by PHID.";
  }

  public function defineParamTypes() {
    return array(
      'phid' => 'required phid',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-USER' => 'No such user exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $user = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $request->getValue('phid'));

    if (!$user) {
      throw new ConduitException('ERR-BAD-USER');
    }

    return $this->buildUserInformationDictionary($user);
  }

}
