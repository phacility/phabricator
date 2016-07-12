<?php

final class PhabricatorUserRolesField
  extends PhabricatorUserCustomField {

  private $value;

  public function getFieldKey() {
    return 'user:roles';
  }

  public function getFieldName() {
    return pht('Roles');
  }

  public function getFieldDescription() {
    return pht('Shows roles like "Administrator" and "Disabled".');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewValue(array $handles) {
    $user = $this->getObject();

    $roles = array();
    if ($user->getIsAdmin()) {
      $roles[] = pht('Administrator');
    }
    if ($user->getIsDisabled()) {
      $roles[] = pht('Disabled');
    }
    if (!$user->getIsApproved()) {
      $roles[] = pht('Not Approved');
    }
    if ($user->getIsSystemAgent()) {
      $roles[] = pht('Bot');
    }
    if ($user->getIsMailingList()) {
      $roles[] = pht('Mailing List');
    }

    if ($roles) {
      return implode(', ', $roles);
    }

    return null;
  }

}
