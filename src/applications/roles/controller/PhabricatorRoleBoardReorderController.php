<?php

final class PhabricatorRoleBoardReorderController
  extends PhabricatorRoleBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $roleid = $request->getURIData('roleID');

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($roleid))
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }

    $this->setRole($role);
    $role_id = $role->getID();

    $view_uri = $this->getApplicationURI("board/{$role_id}/");
    $reorder_uri = $this->getApplicationURI("board/{$role_id}/reorder/");

    if ($request->isFormPost()) {
      // User clicked "Done", make sure the page reloads to show the new
      // column order.
      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    $columns = id(new PhabricatorRoleColumnQuery())
      ->setViewer($viewer)
      ->withRolePHIDs(array($role->getPHID()))
      ->execute();
    $columns = msort($columns, 'getSequence');

    $column_phid = $request->getStr('columnPHID');
    if ($column_phid && $request->validateCSRF()) {

      $columns = mpull($columns, null, 'getPHID');
      if (empty($columns[$column_phid])) {
        return new Aphront404Response();
      }

      $target_column = $columns[$column_phid];
      $new_sequence = $request->getInt('sequence');
      if ($new_sequence < 0) {
        return new Aphront404Response();
      }

      // TODO: For now, we're not recording any transactions here. We probably
      // should, but this sort of edit is extremely trivial.

      // Resequence the columns so that the moved column has the correct
      // sequence number. Move columns after it up one place in the sequence.
      $new_map = array();
      foreach ($columns as $phid => $column) {
        $value = $column->getSequence();
        if ($column->getPHID() == $column_phid) {
          $value = $new_sequence;
        } else if ($column->getSequence() >= $new_sequence) {
          $value = $value + 1;
        }
        $new_map[$phid] = $value;
      }

      // Sort the columns into their new ordering.
      asort($new_map);

      // Now, compact the ordering and adjust any columns that need changes.
      $role->openTransaction();
        $sequence = 0;
        foreach ($new_map as $phid => $ignored) {
          $new_value = $sequence++;
          $cur_value = $columns[$phid]->getSequence();
          if ($new_value != $cur_value) {
            $columns[$phid]->setSequence($new_value)->save();
          }
        }
      $role->saveTransaction();

      return id(new AphrontAjaxResponse())->setContent(
        array(
          'sequenceMap' => mpull($columns, 'getSequence', 'getPHID'),
        ));
    }

    $list_id = celerity_generate_unique_node_id();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setID($list_id)
      ->setFlush(true)
      ->setDrag(true);

    foreach ($columns as $column) {
      // Don't allow milestone columns to be reordered.
      $proxy = $column->getProxy();
      if ($proxy && $proxy->isMilestone()) {
        continue;
      }

      // At least for now, don't show subrole column.
      if ($proxy) {
        continue;
      }

      $item = id(new PHUIObjectItemView())
        ->setHeader($column->getDisplayName())
        ->addIcon($column->getHeaderIcon(), $column->getDisplayType());

      if ($column->isHidden()) {
        $item->setDisabled(true);
      }

      $item->setGrippable(true);
      $item->addSigil('board-column');
      $item->setMetadata(
        array(
          'columnPHID' => $column->getPHID(),
          'columnSequence' => $column->getSequence(),
        ));

      $list->addItem($item);
    }

    Javelin::initBehavior(
      'reorder-columns',
      array(
        'listID' => $list_id,
        'reorderURI' => $reorder_uri,
      ));

    return $this->newDialog()
      ->setTitle(pht('Reorder Columns'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($list)
      ->addSubmitButton(pht('Done'));
  }

}
