<?php

final class PhabricatorStandardCustomFieldUsers
  extends PhabricatorStandardCustomFieldPHIDs {

  public function getFieldType() {
    return 'users';
  }

  public function renderEditControl() {
    $handles = array();
    $value = $this->getFieldValue();
    if ($value) {

      // TODO: Surface and batch.

      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($value)
        ->execute();
    }

    $control = id(new AphrontFormTokenizerControl())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setDatasource('/typeahead/common/accounts/')
      ->setValue($handles);

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

}
