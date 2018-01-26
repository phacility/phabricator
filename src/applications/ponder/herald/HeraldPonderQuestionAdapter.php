<?php

final class HeraldPonderQuestionAdapter extends HeraldAdapter {

  private $question;

  protected function newObject() {
    return new PonderQuestion();
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorPonderApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to questions being created or updated.');
  }

  protected function initializeNewAdapter() {
    $this->question = $this->newObject();
  }


  public function isTestAdapterForObject($object) {
    return ($object instanceof PonderQuestion);
  }

  public function getAdapterTestDescription() {
    return pht(
      'Test rules which run when a question is created or updated.');
  }

  public function setObject($object) {
    $this->question = $object;
    return $this;
  }

  public function supportsApplicationEmail() {
    return true;
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

  public function setQuestion(PonderQuestion $question) {
    $this->question = $question;
    return $this;
  }

  public function getObject() {
    return $this->question;
  }

  public function getAdapterContentName() {
    return pht('Ponder Questions');
  }

  public function getHeraldName() {
    return 'Q'.$this->getObject()->getID();
  }

}
