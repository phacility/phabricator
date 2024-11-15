<?php

final class RoleAddRolesEmailCommand
  extends MetaMTAEmailTransactionCommand {

  public function getCommand() {
    return 'roles';
  }

  public function getCommandSyntax() {
    return '**!roles** //#role ...//';
  }

  public function getCommandSummary() {
    return pht('Add related roles.');
  }

  public function getCommandDescription() {
    return pht(
      'Add one or more roles to the object by listing their hashtags. '.
      'Separate roles with spaces. For example, use `!roles #ios '.
      '#feature` to add both related roles.'.
      "\n\n".
      'Roles which are invalid or unrecognized will be ignored. This '.
      'command has no effect if you do not specify any roles.');
  }

  public function getCommandAliases() {
    return array(
      'role',
    );
  }

  public function isCommandSupportedForObject(
    PhabricatorApplicationTransactionInterface $object) {
    return ($object instanceof PhabricatorRoleInterface);
  }

  public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv) {

    $role_phids = id(new PhabricatorObjectListQuery())
      ->setViewer($viewer)
      ->setAllowedTypes(
        array(
          PhabricatorRoleRolePHIDType::TYPECONST,
        ))
      ->setObjectList(implode(' ', $argv))
      ->setAllowPartialResults(true)
      ->execute();

    $xactions = array();

    $type_role = PhabricatorRoleObjectHasRoleEdgeType::EDGECONST;
    $xactions[] = $object->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $type_role)
      ->setNewValue(
        array(
          '+' => array_fuse($role_phids),
        ));

    return $xactions;
  }

}
