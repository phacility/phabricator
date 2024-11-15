<?php

final class RoleCreateConduitAPIMethod extends RoleConduitAPIMethod {

  public function getAPIMethodName() {
    return 'role.create';
  }

  public function getMethodDescription() {
    return pht('Create a role.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "role.edit" instead.');
  }

  protected function defineParamTypes() {
    return array(
      'name'       => 'required string',
      'members'    => 'optional list<phid>',
      'icon'       => 'optional string',
      'color'      => 'optional string',
      'tags'       => 'optional list<string>',
    );
  }

  protected function defineReturnType() {
    return 'dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();

    $this->requireApplicationCapability(
      RoleCreateRolesCapability::CAPABILITY,
      $user);

    $role = PhabricatorRole::initializeNewRole($user);
    $type_name = PhabricatorRoleNameTransaction::TRANSACTIONTYPE;
    $members = $request->getValue('members');
    $xactions = array();

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType($type_name)
      ->setNewValue($request->getValue('name'));

    if ($request->getValue('icon')) {
      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(
            PhabricatorRoleIconTransaction::TRANSACTIONTYPE)
        ->setNewValue($request->getValue('icon'));
    }

    if ($request->getValue('color')) {
      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(
          PhabricatorRoleColorTransaction::TRANSACTIONTYPE)
        ->setNewValue($request->getValue('color'));
    }

    if ($request->getValue('tags')) {
      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(
            PhabricatorRoleSlugsTransaction::TRANSACTIONTYPE)
        ->setNewValue($request->getValue('tags'));
    }

    $xactions[] = id(new PhabricatorRoleTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        PhabricatorRoleRoleHasMemberEdgeType::EDGECONST)
      ->setNewValue(
        array(
          '+' => array_fuse($members),
        ));

    $editor = id(new PhabricatorRoleTransactionEditor())
      ->setActor($user)
      ->setContinueOnNoEffect(true)
      ->setContentSource($request->newContentSource());

    $editor->applyTransactions($role, $xactions);

    return $this->buildRoleInfoDictionary($role);
  }

}
