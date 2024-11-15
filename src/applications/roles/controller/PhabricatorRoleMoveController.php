<?php

final class PhabricatorRoleMoveController
  extends PhabricatorRoleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $request->validateCSRF();

    $column_phid = $request->getStr('columnPHID');
    $object_phid = $request->getStr('objectPHID');

    $after_phids = $request->getStrList('afterPHIDs');
    $before_phids = $request->getStrList('beforePHIDs');

    $order = $request->getStr('order');
    if ($order === null || $order === '') {
      $order = PhabricatorRoleColumnNaturalOrder::ORDERKEY;
    }

    $ordering = PhabricatorRoleColumnOrder::getOrderByKey($order);
    $ordering = id(clone $ordering)
      ->setViewer($viewer);

    $edit_header = null;
    $raw_header = $request->getStr('header');
    if ($raw_header !== null && $raw_header !== '') {
      $edit_header = phutil_json_decode($raw_header);
    } else {
      $edit_header = array();
    }

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->withIDs(array($id))
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }

    $cancel_uri = $this->getApplicationURI(
      new PhutilURI(
        urisprintf('board/%d/', $role->getID()),
        array(
          'order' => $order,
        )));

    $board_phid = $role->getPHID();

    $object = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->needRolePHIDs(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if (!$object) {
      return new Aphront404Response();
    }

    $columns = id(new PhabricatorRoleColumnQuery())
      ->setViewer($viewer)
      ->withRolePHIDs(array($role->getPHID()))
      ->needTriggers(true)
      ->execute();

    $columns = mpull($columns, null, 'getPHID');
    $column = idx($columns, $column_phid);
    if (!$column) {
      // User is trying to drop this object into a nonexistent column, just kick
      // them out.
      return new Aphront404Response();
    }

    $engine = id(new PhabricatorRoleBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs(array($board_phid))
      ->setObjectPHIDs(array($object_phid))
      ->executeLayout();

    $order_params = array(
      'afterPHIDs' => $after_phids,
      'beforePHIDs' => $before_phids,
    );

    $xactions = array();
    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COLUMNS)
      ->setNewValue(
        array(
          array(
            'columnPHID' => $column->getPHID(),
          ) + $order_params,
        ));

    $header_xactions = $ordering->getColumnTransactions(
      $object,
      $edit_header);
    foreach ($header_xactions as $header_xaction) {
      $xactions[] = $header_xaction;
    }

    $sounds = array();
    if ($column->canHaveTrigger()) {
      $trigger = $column->getTrigger();
      if ($trigger) {
        $trigger_xactions = $trigger->newDropTransactions(
          $viewer,
          $column,
          $object);
        foreach ($trigger_xactions as $trigger_xaction) {
          $xactions[] = $trigger_xaction;
        }

        foreach ($trigger->getSoundEffects() as $effect) {
          $sounds[] = $effect;
        }
      }
    }

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true)
      ->setContentSourceFromRequest($request)
      ->setCancelURI($cancel_uri);

    $editor->applyTransactions($object, $xactions);

    return $this->newCardResponse(
      $board_phid,
      $object_phid,
      $ordering,
      $sounds);
  }

}
