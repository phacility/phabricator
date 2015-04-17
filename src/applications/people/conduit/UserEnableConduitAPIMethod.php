<?php

final class UserEnableConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.enable';
  }

  public function getMethodDescription() {
    return 'Re-enable specified users (admin only).';
  }

  protected function defineParamTypes() {
    return array(
      'phids' => 'required list<phid>',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-PERMISSIONS' => 'Only admins can call this method.',
      'ERR-BAD-PHID' => 'Non existent user PHID.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $actor = $request->getUser();
    if (!$actor->getIsAdmin()) {
      throw new ConduitException('ERR-PERMISSIONS');
    }

    $phids = $request->getValue('phids');

    $users = id(new PhabricatorUser())->loadAllWhere(
      'phid IN (%Ls)',
      $phids);

    if (count($phids) != count($users)) {
      throw new ConduitException('ERR-BAD-PHID');
    }

    foreach ($users as $user) {
      id(new PhabricatorUserEditor())
        ->setActor($actor)
        ->disableUser($user, false);
    }
  }

}
