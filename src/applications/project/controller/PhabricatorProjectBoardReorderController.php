<?php

final class PhabricatorProjectBoardReorderController
  extends PhabricatorProjectBoardController {

  private $projectID;

  public function willProcessRequest(array $data) {
    $this->projectID = $data['projectID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($this->projectID))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $this->setProject($project);


    $project_id = $project->getID();

    $board_uri = $this->getApplicationURI("board/{$project_id}/");
    $reorder_uri = $this->getApplicationURI("board/{$project_id}/reorder/");

    if ($request->isFormPost()) {
      // User clicked "Done", make sure the page reloads to show the new
      // column order.
      return id(new AphrontRedirectResponse())->setURI($board_uri);
    }

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
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
      $project->openTransaction();
        $sequence = 0;
        foreach ($new_map as $phid => $ignored) {
          $new_value = $sequence++;
          $cur_value = $columns[$phid]->getSequence();
          if ($new_value != $cur_value) {
            $columns[$phid]->setSequence($new_value)->save();
          }
        }
      $project->saveTransaction();

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
      ->setStackable(true);

    foreach ($columns as $column) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($column->getDisplayName())
        ->addIcon('none', $column->getDisplayType());

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
      ->appendParagraph(pht('Drag and drop columns to reorder them.'))
      ->appendChild($list)
      ->addSubmitButton(pht('Done'));
  }

}
