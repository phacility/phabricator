<?php

abstract class PhabricatorStandardCustomFieldTokenizer
  extends PhabricatorStandardCustomFieldPHIDs {

  abstract public function getDatasource();

  public function renderEditControl(array $handles) {
    $value = $this->getFieldValue();

    $control = id(new AphrontFormTokenizerControl())
      ->setUser($this->getViewer())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setDatasource($this->getDatasource())
      ->setCaption($this->getCaption())
      ->setError($this->getFieldError())
      ->setValue(nonempty($value, array()));

    $limit = $this->getFieldConfigValue('limit');
    if ($limit) {
      $control->setLimit($limit);
    }

    return $control;
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value) {

    $control = id(new AphrontFormTokenizerControl())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setDatasource($this->getDatasource())
      ->setValue(nonempty($value, array()));

    $form->appendControl($control);
  }

  public function getHeraldFieldValueType($condition) {
    return id(new HeraldTokenizerFieldValue())
      ->setKey('custom.'.$this->getFieldKey())
      ->setDatasource($this->getDatasource());
  }

  public function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_PHID_LIST;
  }

  public function getHeraldDatasource() {
    return $this->getDatasource();
  }

  protected function getHTTPParameterType() {
    return new AphrontPHIDListHTTPParameterType();
  }

  protected function newConduitSearchParameterType() {
    return new ConduitPHIDListParameterType();
  }

  protected function newConduitEditParameterType() {
    return new ConduitPHIDListParameterType();
  }

}
