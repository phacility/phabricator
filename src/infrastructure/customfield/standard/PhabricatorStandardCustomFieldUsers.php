<?php

final class PhabricatorStandardCustomFieldUsers
  extends PhabricatorStandardCustomFieldPHIDs {

  public function getFieldType() {
    return 'users';
  }

  public function renderEditControl(array $handles) {
    $value = $this->getFieldValue();
    if ($value) {
      $control_value = array_select_keys($handles, $value);
    } else {
      $control_value = array();
    }

    $control = id(new AphrontFormTokenizerControl())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setDatasource('/typeahead/common/accounts/')
      ->setCaption($this->getCaption())
      ->setValue($control_value);

    $limit = $this->getFieldConfigValue('limit');
    if ($limit) {
      $control->setLimit($limit);
    }

    return $control;
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value,
    array $handles) {

    $control = id(new AphrontFormTokenizerControl())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setDatasource('/typeahead/common/accounts/')
      ->setValue($handles);

    $form->appendChild($control);
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_USER;
  }

}
