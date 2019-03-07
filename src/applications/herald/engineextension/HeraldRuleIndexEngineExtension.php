<?php

final class HeraldRuleIndexEngineExtension
  extends PhabricatorIndexEngineExtension {

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

  public function indexObject(
    PhabricatorIndexEngine $engine,
    $object) {

    $edge_type = HeraldRuleActionAffectsObjectEdgeType::EDGECONST;

    $old_edges = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      $edge_type);
    $old_edges = array_fuse($old_edges);

    $new_edges = $this->getPHIDsAffectedByActions($object);
    $new_edges = array_fuse($new_edges);

    $add_edges = array_diff_key($new_edges, $old_edges);
    $rem_edges = array_diff_key($old_edges, $new_edges);

    if (!$add_edges && !$rem_edges) {
      return;
    }

    $editor = new PhabricatorEdgeEditor();

    foreach ($add_edges as $phid) {
      $editor->addEdge($object->getPHID(), $edge_type, $phid);
    }

    foreach ($rem_edges as $phid) {
      $editor->removeEdge($object->getPHID(), $edge_type, $phid);
    }

    $editor->save();
  }

  public function getIndexVersion($object) {
    $phids = $this->getPHIDsAffectedByActions($object);
    sort($phids);
    $phids = implode(':', $phids);
    return PhabricatorHash::digestForIndex($phids);
  }

  private function getPHIDsAffectedByActions(HeraldRule $rule) {
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
