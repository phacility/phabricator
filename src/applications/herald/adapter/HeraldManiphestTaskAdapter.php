<?php

/**
 * @group herald
 */
final class HeraldManiphestTaskAdapter extends HeraldAdapter {

  private $task;
  private $ccPHIDs = array();

  public function setTask(ManiphestTask $task) {
    $this->task = $task;
    return $this;
  }
  public function getTask() {
    return $this->task;
  }

  private function setCcPHIDs(array $cc_phids) {
    $this->ccPHIDs = $cc_phids;
    return $this;
  }
  public function getCcPHIDs() {
    return $this->ccPHIDs;
  }

  public function getAdapterContentName() {
    return pht('Maniphest Tasks');
  }

  public function getFields() {
    return array(
      self::FIELD_TITLE,
      self::FIELD_BODY,
      self::FIELD_AUTHOR,
      self::FIELD_CC,
      self::FIELD_CONTENT_SOURCE,
    );
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
    return $this->getTask()->getPHID();
  }

  public function getHeraldName() {
    return 'T'.$this->getTask()->getID();
  }

  public function getHeraldField($field) {
    switch ($field) {
      case self::FIELD_TITLE:
        return $this->getTask()->getTitle();
      case self::FIELD_BODY:
        return $this->getTask()->getDescription();
      case self::FIELD_AUTHOR:
        return $this->getTask()->getAuthorPHID();
      case self::FIELD_CC:
        return $this->getTask()->getCCPHIDs();
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
          $add_cc = array();
          foreach ($effect->getTarget() as $phid) {
            $add_cc[$phid] = true;
          }
          $this->setCcPHIDs(array_keys($add_cc));
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Added address to cc list.'));
          break;
        case self::ACTION_FLAG:
          $result[] = parent::applyFlagEffect(
            $effect,
            $this->getTask()->getPHID());
          break;
        default:
          throw new Exception("No rules to handle action '{$action}'.");
      }
    }
    return $result;
  }
}
