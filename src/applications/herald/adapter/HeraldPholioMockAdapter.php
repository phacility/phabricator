<?php

final class HeraldPholioMockAdapter extends HeraldAdapter {

  private $mock;

  public function getAdapterApplicationClass() {
    return 'PhabricatorPholioApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to mocks being created or updated.');
  }

  protected function newObject() {
    return new PholioMock();
  }

  public function getObject() {
    return $this->mock;
  }

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }
  public function getMock() {
    return $this->mock;
  }

  public function getAdapterContentName() {
    return pht('Pholio Mocks');
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

  public function getFields() {
    return array_merge(
      array(
        self::FIELD_TITLE,
        self::FIELD_BODY,
        self::FIELD_AUTHOR,
        self::FIELD_CC,
        self::FIELD_PROJECTS,
        self::FIELD_IS_NEW_OBJECT,
        self::FIELD_SPACE,
      ),
      parent::getFields());
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array_merge(
          array(
            self::ACTION_ADD_CC,
            self::ACTION_REMOVE_CC,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array_merge(
          array(
            self::ACTION_ADD_CC,
            self::ACTION_REMOVE_CC,
            self::ACTION_FLAG,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
    }
  }

  public function getPHID() {
    return $this->getMock()->getPHID();
  }

  public function getHeraldName() {
    return 'M'.$this->getMock()->getID();
  }

  public function getHeraldField($field) {
    switch ($field) {
      case self::FIELD_TITLE:
        return $this->getMock()->getName();
      case self::FIELD_BODY:
        return $this->getMock()->getDescription();
      case self::FIELD_AUTHOR:
        return $this->getMock()->getAuthorPHID();
      case self::FIELD_PROJECTS:
        return PhabricatorEdgeQuery::loadDestinationPHIDs(
          $this->getMock()->getPHID(),
          PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
    }

    return parent::getHeraldField($field);
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');

    $result = array();
    foreach ($effects as $effect) {
      $action = $effect->getAction();
      switch ($action) {
        default:
          $result[] = $this->applyStandardEffect($effect);
          break;
      }
    }
    return $result;
  }

}
