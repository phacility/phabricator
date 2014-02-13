<?php

abstract class HeraldPreCommitAdapter extends HeraldAdapter {

  private $log;
  private $hookEngine;

  public function setPushLog(PhabricatorRepositoryPushLog $log) {
    $this->log = $log;
    return $this;
  }

  public function setHookEngine(DiffusionCommitHookEngine $engine) {
    $this->hookEngine = $engine;
    return $this;
  }

  public function getHookEngine() {
    return $this->hookEngine;
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorApplicationDiffusion';
  }

  public function getObject() {
    return $this->log;
  }

  public function supportsRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
        return true;
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
      default:
        return false;
    }
  }

  public function canTriggerOnObject($object) {
    if ($object instanceof PhabricatorRepository) {
      return true;
    }

    if ($object instanceof PhabricatorProject) {
      return true;
    }

    return false;
  }

  public function explainValidTriggerObjects() {
    return pht(
      'This rule can trigger for **repositories** or **projects**.');
  }

  public function getTriggerObjectPHIDs() {
    return array_merge(
      array(
        $this->hookEngine->getRepository()->getPHID(),
        $this->getPHID(),
      ),
      $this->hookEngine->getRepository()->getProjectPHIDs());
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
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

  public function getPHID() {
    return $this->getObject()->getPHID();
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
