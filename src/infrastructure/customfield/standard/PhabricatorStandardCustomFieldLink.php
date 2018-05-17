<?php

final class PhabricatorStandardCustomFieldLink
  extends PhabricatorStandardCustomField {

  public function getFieldType() {
    return 'link';
  }

  public function buildFieldIndexes() {
    $indexes = array();

    $value = $this->getFieldValue();
    if (strlen($value)) {
      $indexes[] = $this->newStringIndex($value);
    }

    return $indexes;
  }

  public function renderPropertyViewValue(array $handles) {
    $value = $this->getFieldValue();

    if (!strlen($value)) {
      return null;
    }

    if (!PhabricatorEnv::isValidRemoteURIForLink($value)) {
      return $value;
    }

    return phutil_tag(
      'a',
      array(
        'href' => $value,
        'target' => '_blank',
        'rel' => 'noreferrer',
      ),
      $value);
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

    if (is_string($value) && !strlen($value)) {
      return;
    }

    $value = (array)$value;
    if ($value) {
      $query->withApplicationSearchContainsConstraint(
        $this->newStringIndex(null),
        $value);
    }
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value) {

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setLabel($this->getFieldName())
        ->setName($this->getFieldKey())
        ->setValue($value));
  }

  public function shouldAppearInHerald() {
    return true;
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_CONTAINS,
      HeraldAdapter::CONDITION_NOT_CONTAINS,
      HeraldAdapter::CONDITION_IS,
      HeraldAdapter::CONDITION_IS_NOT,
      HeraldAdapter::CONDITION_REGEXP,
      HeraldAdapter::CONDITION_NOT_REGEXP,
    );
  }

  public function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_TEXT;
  }

  protected function getHTTPParameterType() {
    return new AphrontStringHTTPParameterType();
  }

  protected function newConduitSearchParameterType() {
    return new ConduitStringListParameterType();
  }

  protected function newConduitEditParameterType() {
    return new ConduitStringParameterType();
  }

}
