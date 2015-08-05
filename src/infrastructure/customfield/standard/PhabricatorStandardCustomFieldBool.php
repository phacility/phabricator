<?php

final class PhabricatorStandardCustomFieldBool
  extends PhabricatorStandardCustomField {

  public function getFieldType() {
    return 'bool';
  }

  public function buildFieldIndexes() {
    $indexes = array();

    $value = $this->getFieldValue();
    if (strlen($value)) {
      $indexes[] = $this->newNumericIndex((int)$value);
    }

    return $indexes;
  }

  public function buildOrderIndex() {
    return $this->newNumericIndex(0);
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->setFieldValue((bool)$request->getBool($this->getFieldKey()));
  }

  public function getValueForStorage() {
    $value = $this->getFieldValue();
    if ($value !== null) {
      return (int)$value;
    } else {
      return null;
    }
  }

  public function setValueFromStorage($value) {
    if (strlen($value)) {
      $value = (bool)$value;
    } else {
      $value = null;
    }
    return $this->setFieldValue($value);
  }

  public function readApplicationSearchValueFromRequest(
    PhabricatorApplicationSearchEngine $engine,
    AphrontRequest $request) {

    return $request->getStr($this->getFieldKey());
  }

  public function applyApplicationSearchConstraintToQuery(
    PhabricatorApplicationSearchEngine $engine,
    PhabricatorCursorPagedPolicyAwareQuery $query,
    $value) {
    if ($value == 'require') {
      $query->withApplicationSearchContainsConstraint(
        $this->newNumericIndex(null),
        1);
    }
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value) {

    $form->appendChild(
      id(new AphrontFormSelectControl())
        ->setLabel($this->getFieldName())
        ->setName($this->getFieldKey())
        ->setValue($value)
        ->setOptions(
          array(
            ''  => $this->getString('search.default', pht('(Any)')),
            'require' => $this->getString('search.require', pht('Require')),
          )));
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormCheckboxControl())
      ->setLabel($this->getFieldName())
      ->setCaption($this->getCaption())
      ->addCheckbox(
        $this->getFieldKey(),
        1,
        $this->getString('edit.checkbox'),
        (bool)$this->getFieldValue());
  }

  public function renderPropertyViewValue(array $handles) {
    $value = $this->getFieldValue();
    if ($value) {
      return $this->getString('view.yes', pht('Yes'));
    } else {
      return null;
    }
  }

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {
    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    if ($new) {
      return pht(
        '%s checked %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName());
    } else {
      return pht(
        '%s unchecked %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName());
    }
  }

  public function shouldAppearInHerald() {
    return true;
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_IS_TRUE,
      HeraldAdapter::CONDITION_IS_FALSE,
    );
  }

}
