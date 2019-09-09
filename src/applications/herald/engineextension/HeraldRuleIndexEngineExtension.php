<?php

final class HeraldRuleIndexEngineExtension
  extends PhabricatorEdgeIndexEngineExtension {

  const EXTENSIONKEY = 'herald.actions';

  public function getExtensionName() {
    return pht('Herald Actions');
  }

  public function shouldIndexObject($object) {
    if (!($object instanceof HeraldRule)) {
      return false;
    }

    return true;
  }

  protected function getIndexEdgeType() {
    return HeraldRuleActionAffectsObjectEdgeType::EDGECONST;
  }

  protected function getIndexDestinationPHIDs($object) {
    $rule = $object;

    $viewer = $this->getViewer();

    $rule = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withIDs(array($rule->getID()))
      ->needConditionsAndActions(true)
      ->executeOne();
    if (!$rule) {
      return array();
    }

    $phids = array();

    $fields = HeraldField::getAllFields();
    foreach ($rule->getConditions() as $condition_record) {
      $field = idx($fields, $condition_record->getFieldName());

      if (!$field) {
        continue;
      }

      $affected_phids = $field->getPHIDsAffectedByCondition($condition_record);
      foreach ($affected_phids as $phid) {
        $phids[] = $phid;
      }
    }

    $actions = HeraldAction::getAllActions();
    foreach ($rule->getActions() as $action_record) {
      $action = idx($actions, $action_record->getAction());

      if (!$action) {
        continue;
      }

      foreach ($action->getPHIDsAffectedByAction($action_record) as $phid) {
        $phids[] = $phid;
      }
    }

    $phids = array_fuse($phids);
    return array_keys($phids);
  }

}
