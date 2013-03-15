<?php

final class ConduitAPI_releephwork_getauthorinfo_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Return a string to use as the VCS author.";
  }

  public function defineParamTypes() {
    return array(
      'userPHID'  => 'required string',
      'vcsType'   => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty string';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = id(new PhabricatorUser())
      ->loadOneWhere('phid = %s', $request->getValue('userPHID'));

    $email = $user->loadPrimaryEmailAddress();
    if (is_numeric($email)) {
      $email = $user->getUserName().'@fb.com';
    }

    return sprintf(
      '%s <%s>',
      $user->getRealName(),
      $email);
  }
}
