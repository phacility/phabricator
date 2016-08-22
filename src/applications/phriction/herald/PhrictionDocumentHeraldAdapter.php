<?php

final class PhrictionDocumentHeraldAdapter extends HeraldAdapter {

  private $document;

  public function getAdapterApplicationClass() {
    return 'PhabricatorPhrictionApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to wiki documents being created or updated.');
  }

  protected function initializeNewAdapter() {
    $this->document = $this->newObject();
  }

  protected function newObject() {
    return new PhrictionDocument();
  }

  public function isTestAdapterForObject($object) {
    return ($object instanceof PhrictionDocument);
  }

  public function getAdapterTestDescription() {
    return pht(
      'Test rules which run when a wiki document is created or updated.');
  }

  public function setObject($object) {
    $this->document = $object;
    return $this;
  }

  public function getObject() {
    return $this->document;
  }

  public function setDocument(PhrictionDocument $document) {
    $this->document = $document;
    return $this;
  }

  public function getDocument() {
    return $this->document;
  }

  public function getAdapterContentName() {
    return pht('Phriction Documents');
  }

  public function supportsRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return true;
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
      default:
        return false;
    }
  }

  public function getHeraldName() {
    return pht('Wiki Document %d', $this->getDocument()->getID());
  }

}
