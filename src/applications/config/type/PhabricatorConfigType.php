<?php

abstract class PhabricatorConfigType extends Phobject {

  final public function getTypeKey() {
    return $this->getPhobjectClassConstant('TYPEKEY');
  }

  final public static function getAllTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getTypeKey')
      ->execute();
  }

  public function isValuePresentInRequest(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {
    $http_type = $this->newHTTPParameterType();
    return $http_type->getExists($request, 'value');
  }

  public function readValueFromRequest(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {
    $http_type = $this->newHTTPParameterType();
    return $http_type->getValue($request, 'value');
  }

  abstract protected function newHTTPParameterType();

  public function newTransaction(
    PhabricatorConfigOption $option,
    $value) {

    $xaction_value = $this->newTransactionValue($option, $value);

    return id(new PhabricatorConfigTransaction())
      ->setTransactionType(PhabricatorConfigTransaction::TYPE_EDIT)
      ->setNewValue(
        array(
          'deleted' => false,
          'value' => $xaction_value,
        ));
  }

  protected function newTransactionValue(
    PhabricatorConfigOption $option,
    $value) {
    return $value;
  }

  public function newDisplayValue(
    PhabricatorConfigOption $option,
    $value) {
    return $value;
  }

  public function newControls(
    PhabricatorConfigOption $option,
    $value,
    $error) {

    $control = $this->newControl($option)
      ->setError($error)
      ->setLabel(pht('Database Value'))
      ->setName('value');

    $value = $this->newControlValue($option, $value);
    $control->setValue($value);

    return array(
      $control,
    );
  }

  abstract protected function newControl(PhabricatorConfigOption $option);

  protected function newControlValue(
    PhabricatorConfigOption $option,
    $value) {
    return $value;
  }

  protected function newException($message) {
    return new PhabricatorConfigValidationException($message);
  }

  public function newValueFromRequestValue(
    PhabricatorConfigOption $option,
    $value) {
    return $this->newCanonicalValue($option, $value);
  }

  public function newValueFromCommandLineValue(
    PhabricatorConfigOption $option,
    $value) {
    return $this->newCanonicalValue($option, $value);
  }

  protected function newCanonicalValue(
    PhabricatorConfigOption $option,
    $value) {
    return $value;
  }

  abstract public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value);

}
