<?php

final class HeraldPreCommitRefAdapter extends HeraldAdapter {

  private $log;
  private $hookEngine;

  const FIELD_REF_TYPE = 'ref-type';
  const FIELD_REF_NAME = 'ref-name';
  const FIELD_REF_CHANGE = 'ref-change';

  const VALUE_REF_TYPE = 'value-ref-type';
  const VALUE_REF_CHANGE = 'value-ref-change';

  public function setPushLog(PhabricatorRepositoryPushLog $log) {
    $this->log = $log;
    return $this;
  }

  public function setHookEngine(DiffusionCommitHookEngine $engine) {
    $this->hookEngine = $engine;
    return $this;
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorApplicationDiffusion';
  }

  public function getObject() {
    return $this->log;
  }

  public function getAdapterContentName() {
    return pht('Commit Hook: Branches/Tags/Bookmarks');
  }

  public function getFieldNameMap() {
    return array(
      self::FIELD_REF_TYPE => pht('Ref type'),
      self::FIELD_REF_NAME => pht('Ref name'),
      self::FIELD_REF_CHANGE => pht('Ref change type'),
    ) + parent::getFieldNameMap();
  }

  public function getFields() {
    return array_merge(
      array(
        self::FIELD_REF_TYPE,
        self::FIELD_REF_NAME,
        self::FIELD_REF_CHANGE,
        self::FIELD_REPOSITORY,
        self::FIELD_PUSHER,
        self::FIELD_PUSHER_PROJECTS,
        self::FIELD_RULE,
      ),
      parent::getFields());
  }

  public function getConditionsForField($field) {
    switch ($field) {
      case self::FIELD_REF_NAME:
        return array(
          self::CONDITION_IS,
          self::CONDITION_IS_NOT,
          self::CONDITION_CONTAINS,
          self::CONDITION_REGEXP,
        );
      case self::FIELD_REF_TYPE:
        return array(
          self::CONDITION_IS,
          self::CONDITION_IS_NOT,
        );
      case self::FIELD_REF_CHANGE:
        return array(
          self::CONDITION_HAS_BIT,
          self::CONDITION_NOT_BIT,
        );
    }
    return parent::getConditionsForField($field);
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array(
          self::ACTION_BLOCK,
          self::ACTION_NOTHING
        );
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array(
          self::ACTION_NOTHING,
        );
    }
  }

  public function getValueTypeForFieldAndCondition($field, $condition) {
    switch ($field) {
      case self::FIELD_REF_TYPE:
        return self::VALUE_REF_TYPE;
      case self::FIELD_REF_CHANGE:
        return self::VALUE_REF_CHANGE;
    }

    return parent::getValueTypeForFieldAndCondition($field, $condition);
  }

  public function getPHID() {
    return $this->getObject()->getPHID();
  }

  public function getHeraldName() {
    return pht('Push Log');
  }

  public function getHeraldField($field) {
    $log = $this->getObject();
    switch ($field) {
      case self::FIELD_REF_TYPE:
        return $log->getRefType();
      case self::FIELD_REF_NAME:
        return $log->getRefName();
      case self::FIELD_REF_CHANGE:
        return $log->getChangeFlags();
      case self::FIELD_REPOSITORY:
        return $this->hookEngine->getRepository()->getPHID();
      case self::FIELD_PUSHER:
        return $this->hookEngine->getViewer()->getPHID();
      case self::FIELD_PUSHER_PROJECTS:
        return $this->hookEngine->loadViewerProjectPHIDsForHerald();
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
            pht('Did nothing.'));
          break;
        case self::ACTION_BLOCK:
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Blocked push.'));
          break;
        default:
          throw new Exception(pht('No rules to handle action "%s"!', $action));
      }
    }

    return $result;
  }

}
