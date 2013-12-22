<?php

/**
 * @group herald
 */
final class HeraldManiphestTaskAdapter extends HeraldAdapter {

  private $task;
  private $ccPHIDs = array();
  private $assignPHID;
  private $projectPHIDs = array();

  public function getAdapterApplicationClass() {
    return 'PhabricatorApplicationManiphest';
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

  public function setProjectPHIDs(array $project_phids) {
    $this->projectPHIDs = $project_phids;
    return $this;
  }
  public function getProjectPHIDs() {
    return $this->projectPHIDs;
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
      ),
      parent::getFields());
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array(
          self::ACTION_ADD_CC,
          self::ACTION_ASSIGN_TASK,
          self::ACTION_ADD_PROJECTS,
          self::ACTION_NOTHING,
        );
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array(
          self::ACTION_ADD_CC,
          self::ACTION_FLAG,
          self::ACTION_ASSIGN_TASK,
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
      case self::FIELD_ASSIGNEE:
        return $this->getTask()->getOwnerPHID();
      case self::FIELD_CC:
        return $this->getTask()->getCCPHIDs();
      case self::FIELD_PROJECTS:
        return $this->getTask()->getProjectPHIDs();
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
            $this->getTask()->getPHID());
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
        case self::ACTION_ADD_PROJECTS:
          foreach ($effect->getTarget() as $phid) {
            $this->projectPHIDs[] = $phid;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Added projects.'));
          break;
        default:
          throw new Exception("No rules to handle action '{$action}'.");
      }
    }
    return $result;
  }
}
