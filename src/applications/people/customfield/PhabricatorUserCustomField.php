<?php

abstract class PhabricatorUserCustomField
  extends PhabricatorCustomField
  implements PhabricatorUserCustomFieldInterface {


  public function shouldEnableForRole($role) {
    switch ($role) {
      case PhabricatorUserCustomFieldInterface::ROLE_EDIT:
        return $this->shouldAppearOnProfileEdit();
    }
    return parent::shouldEnableForRole($role);
  }

  public function shouldAppearOnProfileEdit() {
    return true;
  }


/* -(  PhabricatorCustomField  )--------------------------------------------- */


  public function canDisableField() {
    return false;
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

}
