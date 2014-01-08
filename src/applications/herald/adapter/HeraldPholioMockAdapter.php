<?php

/**
 * @group herald
 */
final class HeraldPholioMockAdapter extends HeraldAdapter {

  private $mock;
  private $ccPHIDs = array();

  public function getAdapterApplicationClass() {
    return 'PhabricatorApplicationPholio';
  }

  public function getAdapterContentDescription() {
    return pht(
      'React to mocks being created or updated.');
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

  private function setCcPHIDs(array $cc_phids) {
    $this->ccPHIDs = $cc_phids;
    return $this;
  }
  public function getCcPHIDs() {
    return $this->ccPHIDs;
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
      ),
      parent::getFields());
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array(
          self::ACTION_ADD_CC,
          self::ACTION_NOTHING,
        );
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array(
          self::ACTION_ADD_CC,
          self::ACTION_FLAG,
          self::ACTION_NOTHING,
        );
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
      case self::FIELD_CC:
        return PhabricatorSubscribersQuery::loadSubscribersForPHID(
                $this->getMock()->getPHID());
    }

    return parent::getHeraldField($field);
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');

    $result = array();
    foreach ($effects as $effect) {
      $action = $effect->getAction();
      switch ($action) {
        case self::ACTION_NOTHING:
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Great success at doing nothing.'));
          break;
        case self::ACTION_ADD_CC:
          foreach ($effect->getTarget() as $phid) {
            $this->ccPHIDs[] = $phid;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Added address to cc list.'));
          break;
        case self::ACTION_FLAG:
          $result[] = parent::applyFlagEffect(
            $effect,
            $this->getMock()->getPHID());
          break;
        default:
          throw new Exception("No rules to handle action '{$action}'.");
      }
    }
    return $result;
  }
}
