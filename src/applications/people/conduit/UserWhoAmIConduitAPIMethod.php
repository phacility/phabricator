<?php

final class UserWhoAmIConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.whoami';
  }

  public function getMethodDescription() {
    return 'Retrieve information about the logged-in user.';
  }

  public function defineParamTypes() {
    return array();
  }

  public function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array();
  }

  public function getRequiredScope() {
    return PhabricatorOAuthServerScope::SCOPE_WHOAMI;
  }

  protected function execute(ConduitAPIRequest $request) {
    return $this->buildUserInformationDictionary($request->getUser());
  }

}
