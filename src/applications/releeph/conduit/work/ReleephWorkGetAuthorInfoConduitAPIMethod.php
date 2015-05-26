<?php

final class ReleephWorkGetAuthorInfoConduitAPIMethod
  extends ReleephConduitAPIMethod {

  public function getAPIMethodName() {
    return 'releephwork.getauthorinfo';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return pht('Return a string to use as the VCS author.');
  }

  protected function defineParamTypes() {
    return array(
      'userPHID'  => 'required string',
      'vcsType'   => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty string';
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
