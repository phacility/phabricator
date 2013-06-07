<?php

interface PhabricatorCustomFieldInterface {

  public function getCustomFieldBaseClass();
  public function getCustomFieldSpecificationForRole($role);
  public function getCustomFields($role);
  public function attachCustomFields($role, array $fields);

}


// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////


/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */
/*

  private $customFields = array();

  public function getCustomFieldSpecificationForRole($role) {
    return PhabricatorEnv::getEnvConfig(<<<'application.fields'>>>);
  }

  public function getCustomFieldBaseClass() {
    return <<<<'YourApplicationHereCustomField'>>>>;
  }

  public function getCustomFields($role) {
    if (idx($this->customFields, $role) === null) {
      PhabricatorCustomField::raiseUnattachedException($this, $role);
    }
    return $this->customFields[$role];
  }

  public function attachCustomFields($role, array $fields) {
    $this->customFields[$role] = $fields;
    return $this;
  }

*/
