<?php

final class PhabricatorSelectExportField
  extends PhabricatorExportField {

  private $phabricatorStandardCustomField;

  function __construct($_phabricatorStandardCustomField){
        $this->phabricatorStandardCustomField = $_phabricatorStandardCustomField;
  }

  

  public function setPhabricatorStandardCustomField($value){
      $this->phabricatorStandardCustomField = $value;
      return $this;
  }

  public function getPhabricatorStandardCustomField(){
    return $this->phabricatorStandardCustomField;
  }

  public function getTextValue($value) {
    $field =  $this->getPhabricatorStandardCustomField();
    $field_Options = $field->getOptions();
    return $field_Options[$value];
  }
}
