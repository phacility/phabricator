<?php

final class PhabricatorLegalpadSignaturePolicyRule
  extends PhabricatorPolicyRule {

  private $signatures = array();

  public function getRuleDescription() {
    return pht('signers of legalpad documents');
  }

  public function willApplyRules(
    PhabricatorUser $viewer,
    array $values,
    array $objects) {

    $values = array_unique(array_filter(array_mergev($values)));
    if (!$values) {
      return;
    }

    // TODO: This accepts signature of any version of the document, even an
    // older version.

    $documents = id(new LegalpadDocumentQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($values)
      ->withSignerPHIDs(array($viewer->getPHID()))
      ->execute();
    $this->signatures = mpull($documents, 'getPHID', 'getPHID');
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    foreach ($value as $document_phid) {
      if (!isset($this->signatures[$document_phid])) {
        return false;
      }
    }

    return true;
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_TOKENIZER;
  }

  public function getValueControlTemplate() {
    return $this->getDatasourceTemplate(new LegalpadDocumentDatasource());
  }

  public function getRuleOrder() {
    return 900;
  }

  public function getValueForStorage($value) {
    PhutilTypeSpec::newFromString('list<string>')->check($value);
    return array_values($value);
  }

  public function getValueForDisplay(PhabricatorUser $viewer, $value) {
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($value)
      ->execute();
    return mpull($handles, 'getFullName', 'getPHID');
  }

  public function ruleHasEffect($value) {
    return (bool)$value;
  }

}
