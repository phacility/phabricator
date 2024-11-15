<?php

final class PhabricatorRoleParentTransaction
  extends PhabricatorRoleTypeTransaction {

  const TRANSACTIONTYPE = 'role:parent';

  public function generateOldValue($object) {
    return null;
  }

  public function applyInternalEffects($object, $value) {
    $parent_phid = $value;
    $role = id(new PhabricatorRoleQuery())
      ->setViewer($this->getActor())
      ->withPHIDs(array($parent_phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    $object->attachParentRole($role);

    $object->setParentRolePHID($value);
  }

}
