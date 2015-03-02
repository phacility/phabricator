<?php

final class UserAddConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.add';
  }

  public function getMethodDescription() {
    return 'Add a user (Admin Only)';
  }

  public function defineParamTypes() {
    return array(
      'username'            => 'required string',
      'realname'            => 'required string',
      'email'               => 'required string',
      'password'            => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-USERNAME-TAKEN' => 'Username is already taken.',
      'ERR-EMAIL-TAKEN'    => 'Duplicate email.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $actor = $request->getUser();
    if (!$actor->getIsAdmin()) {
      throw new ConduitException('ERR-PERMISSIONS');
    }

    $existing_user = id(new PhabricatorUser())
      ->loadOneWhere('username = %s', $request->getValue('username'));
    if ($existing_user) {
      throw new ConduitException('ERR-USERNAME-TAKEN');
    }

    $existing_email = id(new PhabricatorUserEmail())
       ->loadOneWhere('address = %s', $request->getValue('email'));
    if ($existing_email) {
      throw new ConduitException('ERR-EMAIL-TAKEN');
    }

    $user = new PhabricatorUser();
    $user->setUsername($request->getValue('username'));
    $user->setRealname($request->getValue('realname'));
    $user->setIsApproved(1);

    $email_object = id(new PhabricatorUserEmail())
      ->setAddress($request->getValue('email'))
      ->setIsVerified(1);

    id(new PhabricatorUserEditor())
      ->setActor($actor)
      ->createNewUser($user, $email_object);

    // user needs to be saved before password can be set
    $envelope = new PhutilOpaqueEnvelope($request->getValue('password'));
    id(new PhabricatorUserEditor())
      ->setActor($actor)
      ->changePassword($user, $envelope);

    return $this->buildUserInformationDictionary($user);
  }
}
