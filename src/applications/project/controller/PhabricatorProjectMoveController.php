<?php

final class PhabricatorProjectMoveController
  extends PhabricatorProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $column_phid = $request->getStr('columnPHID');
    $object_phid = $request->getStr('objectPHID');
    $after_phid = $request->getStr('afterPHID');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    // NOTE: I'm not requiring EDIT on the object for now, since we require
    // EDIT on the project anyway and this relationship is more owned by the
    // project than the object. Maybe this is worth revisiting eventually.

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->executeOne();

    if (!$object) {
      return new Aphront404Response();
    }

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->execute();

    $columns = mpull($columns, null, 'getPHID');
    $column = idx($columns, $column_phid);
    if (!$column) {
      // User is trying to drop this object into a nonexistent column, just kick
      // them out.
      return new Aphront404Response();
    }

    $xactions = array();

    $edge_type = PhabricatorEdgeConfig::TYPE_OBJECT_HAS_COLUMN;

    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($object->getPHID()))
      ->withEdgeTypes(array($edge_type))
      ->withDestinationPHIDs(array_keys($columns));

    $query->execute();

    $edge_phids = $query->getDestinationPHIDs();

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_PROJECT_COLUMN)
      ->setNewValue(array(
        'columnPHIDs' => array($column->getPHID()),
        'projectPHID' => $column->getProjectPHID()))
      ->setOldValue(array(
        'columnPHIDs' => $edge_phids,
        'projectPHID' => $column->getProjectPHID()));

    if ($after_phid) {
      $after_task = id(new ManiphestTaskQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($after_phid))
        ->requireCapabilities(array(PhabricatorPolicyCapability::CAN_EDIT))
        ->executeOne();
      if (!$after_task) {
        return new Aphront404Response();
      }
      $after_pri = $after_task->getPriority();
      $after_sub = $after_task->getSubpriority();

      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(ManiphestTransaction::TYPE_SUBPRIORITY)
        ->setNewValue(array(
          'newPriority' => $after_pri,
          'newSubpriorityBase' => $after_sub));
    }

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true)
      ->setContentSourceFromRequest($request);

    $editor->applyTransactions($object, $xactions);

    $owner = null;
    if ($object->getOwnerPHID()) {
      $owner = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($object->getOwnerPHID()))
        ->executeOne();
    }
    $card = id(new ProjectBoardTaskCard())
      ->setViewer($viewer)
      ->setTask($object)
      ->setOwner($owner)
      ->setCanEdit(true)
      ->getItem();

    return id(new AphrontAjaxResponse())->setContent(
      array('task' => $card));
 }

}
