<?php

interface PhabricatorCustomFieldInterface {

  public function getCustomFieldBaseClass();
  public function getCustomFieldSpecificationForRole($role);
  public function getCustomFields();
  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields);

}


// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////


/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */
/*

  private $customFields = self::ATTACHABLE;

  public function getCustomFieldSpecificationForRole($role) {
    return PhabricatorEnv::getEnvConfig(<<<'application.fields'>>>);
  }

  public function getCustomFieldBaseClass() {
    return <<<<'YourApplicationHereCustomField'>>>>;
  }

  public function getCustomFields() {
    return $this->assertAttached($this->customFields);
  }

  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields) {
    $this->customFields = $fields;
    return $this;
  }

*/
