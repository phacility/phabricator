<?php

final class PhabricatorStandardCustomFieldUsers
  extends PhabricatorStandardCustomFieldPHIDs {

  public function getFieldType() {
    return 'users';
  }

  public function renderEditControl(array $handles) {
    $value = $this->getFieldValue();

    $control = id(new AphrontFormTokenizerControl())
      ->setUser($this->getViewer())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setDatasource(new PhabricatorPeopleDatasource())
      ->setCaption($this->getCaption())
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
      ->setDatasource(new PhabricatorPeopleDatasource())
      ->setValue(nonempty($value, array()));

    $form->appendControl($control);
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_USER;
  }

}
