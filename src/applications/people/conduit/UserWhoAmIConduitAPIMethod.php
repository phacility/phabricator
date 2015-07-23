<?php

final class UserWhoAmIConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.whoami';
  }

  public function getMethodDescription() {
    return pht('Retrieve information about the logged-in user.');
  }

  protected function defineParamTypes() {
    return array();
  }

  protected function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  public function getRequiredScope() {
    return PhabricatorOAuthServerScope::SCOPE_WHOAMI;
  }

  protected function execute(ConduitAPIRequest $request) {
    $person = id(new PhabricatorPeopleQuery())
      ->setViewer($request->getUser())
      ->needProfileImage(true)
      ->withPHIDs(array($request->getUser()->getPHID()))
      ->executeOne();

    return $this->buildUserInformationDictionary(
      $person,
      $with_email = true,
      $with_availability = false);
  }

}
