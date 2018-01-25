<?php

/**
 * Serialize for RuleTransactions / Editor.
 */
final class HeraldRuleSerializer extends Phobject {
  public function serializeRule(HeraldRule $rule) {
    return $this->serializeRuleComponents(
      (bool)$rule->getMustMatchAll(),
      $rule->getConditions(),
      $rule->getActions(),
      $rule->getRepetitionPolicyStringConstant());
  }

  public function serializeRuleComponents(
    $match_all,
    array $conditions,
    array $actions,
    $repetition_policy) {

    assert_instances_of($conditions, 'HeraldCondition');
    assert_instances_of($actions, 'HeraldActionRecord');

    $conditions_array = array();
    foreach ($conditions as $condition) {
      $conditions_array[] = array(
        'field' => $condition->getFieldName(),
        'condition' => $condition->getFieldCondition(),
        'value' => $condition->getValue(),
      );
    }

    $actions_array = array();
    foreach ($actions as $action) {
      $actions_array[] = array(
        'action' => $action->getAction(),
        'target' => $action->getTarget(),
      );
    }

    return array(
      'match_all' => $match_all,
      'conditions' => $conditions_array,
      'actions' => $actions_array,
      'repetition_policy' => $repetition_policy,
    );
  }

  public function deserializeRuleComponents(array $serialized) {
    $deser_conditions = array();
    foreach ($serialized['conditions'] as $condition) {
      $deser_conditions[] = id(new HeraldCondition())
        ->setFieldName($condition['field'])
        ->setFieldCondition($condition['condition'])
        ->setValue($condition['value']);
    }

    $deser_actions = array();
    foreach ($serialized['actions'] as $action) {
      $deser_actions[] = id(new HeraldActionRecord())
        ->setAction($action['action'])
        ->setTarget($action['target']);
    }

    return array(
      'match_all' => $serialized['match_all'],
      'conditions' => $deser_conditions,
      'actions' => $deser_actions,
      'repetition_policy' => $serialized['repetition_policy'],
    );
  }

}
