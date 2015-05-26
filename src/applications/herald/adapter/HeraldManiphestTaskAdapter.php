<?php

final class HeraldManiphestTaskAdapter extends HeraldAdapter {

  private $task;
  private $ccPHIDs = array();
  private $assignPHID;

  protected function newObject() {
    return new ManiphestTask();
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorManiphestApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to tasks being created or updated.');
  }

  public function getRepetitionOptions() {
    return array(
      HeraldRepetitionPolicyConfig::EVERY,
      HeraldRepetitionPolicyConfig::FIRST,
    );
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

  public function setTask(ManiphestTask $task) {
    $this->task = $task;
    return $this;
  }
  public function getTask() {
    return $this->task;
  }

  public function getObject() {
    return $this->task;
  }

  private function setCcPHIDs(array $cc_phids) {
    $this->ccPHIDs = $cc_phids;
    return $this;
  }
  public function getCcPHIDs() {
    return $this->ccPHIDs;
  }

  public function setAssignPHID($assign_phid) {
    $this->assignPHID = $assign_phid;
    return $this;
  }
  public function getAssignPHID() {
    return $this->assignPHID;
  }

  public function getAdapterContentName() {
    return pht('Maniphest Tasks');
  }

  public function getFields() {
    return array_merge(
      array(
        self::FIELD_TITLE,
        self::FIELD_BODY,
        self::FIELD_AUTHOR,
        self::FIELD_ASSIGNEE,
        self::FIELD_CC,
        self::FIELD_CONTENT_SOURCE,
        self::FIELD_PROJECTS,
        self::FIELD_TASK_PRIORITY,
        self::FIELD_TASK_STATUS,
        self::FIELD_IS_NEW_OBJECT,
        self::FIELD_APPLICATION_EMAIL,
      ),
      parent::getFields());
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array_merge(
          array(
            self::ACTION_ADD_CC,
            self::ACTION_EMAIL,
            self::ACTION_ASSIGN_TASK,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array_merge(
          array(
            self::ACTION_ADD_CC,
            self::ACTION_EMAIL,
            self::ACTION_FLAG,
            self::ACTION_ASSIGN_TASK,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
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
      case self::FIELD_ASSIGNEE:
        return $this->getTask()->getOwnerPHID();
      case self::FIELD_CC:
        return PhabricatorSubscribersQuery::loadSubscribersForPHID(
          $this->getTask()->getPHID());
      case self::FIELD_PROJECTS:
        return PhabricatorEdgeQuery::loadDestinationPHIDs(
          $this->getTask()->getPHID(),
          PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      case self::FIELD_TASK_PRIORITY:
        return $this->getTask()->getPriority();
      case self::FIELD_TASK_STATUS:
        return $this->getTask()->getStatus();
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
            pht('Added addresses to cc list.'));
          break;
        case self::ACTION_ASSIGN_TASK:
          $target_array = $effect->getTarget();
          $assign_phid = reset($target_array);
          $this->setAssignPHID($assign_phid);
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Assigned task.'));
          break;
        default:
          $result[] = $this->applyStandardEffect($effect);
          break;
      }
    }
    return $result;
  }

}
