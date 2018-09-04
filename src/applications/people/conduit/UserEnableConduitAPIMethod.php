<?php

final class UserEnableConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.enable';
  }

  public function getMethodDescription() {
    return pht('Re-enable specified users (admin only).');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return pht('Obsoleted by method "user.edit".');
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
      'ERR-PERMISSIONS' => pht('Only admins can call this method.'),
      'ERR-BAD-PHID' => pht('Non existent user PHID.'),
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

    foreach ($phids as $phid) {
      $params = array(
        'transactions' => array(
          array(
            'type' => 'disabled',
            'value' => false,
          ),
        ),
        'objectIdentifier' => $phid,
      );

      id(new ConduitCall('user.edit', $params))
        ->setUser($actor)
        ->execute();
    }

    return null;
  }

}
