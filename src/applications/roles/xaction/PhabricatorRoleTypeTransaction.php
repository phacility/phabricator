<?php

abstract class PhabricatorRoleTypeTransaction
  extends PhabricatorRoleTransactionType {

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if (!$xactions) {
      return $errors;
    }

    $xaction = last($xactions);

    $parent_phid = $xaction->getNewValue();
    if (!$parent_phid) {
      return $errors;
    }

    if (!$this->getEditor()->getIsNewObject()) {
      $errors[] = $this->newInvalidError(
        pht(
          'You can only set a parent or milestone role when creating a '.
          'role for the first time.'));
      return $errors;
    }

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($this->getActor())
      ->withPHIDs(array($parent_phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();
    if (!$roles) {
      $errors[] = $this->newInvalidError(
        pht(
          'Parent or milestone role PHID ("%s") must be the PHID of a '.
          'valid, visible role which you have permission to edit.',
          $parent_phid));
      return $errors;
    }

    $role = head($roles);

    if ($role->isMilestone()) {
      $errors[] = $this->newInvalidError(
        pht(
          'Parent or milestone role PHID ("%s") must not be a '.
          'milestone. Milestones may not have subroles or milestones.',
          $parent_phid));
      return $errors;
    }

    $limit = PhabricatorRole::getRoleDepthLimit();
    if ($role->getRoleDepth() >= ($limit - 1)) {
      $errors[] = $this->newInvalidError(
        pht(
          'You can not create a subrole or milestone under this parent '.
          'because it would nest roles too deeply. The maximum '.
          'nesting depth of roles is %s.',
          new PhutilNumber($limit)));
      return $errors;
    }

    return $errors;
  }

}
