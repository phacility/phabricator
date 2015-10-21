<?php

final class DrydockRepositoryOperationStatusView
  extends AphrontView {

  private $operation;
  private $boxView;

  public function setOperation(DrydockRepositoryOperation $operation) {
    $this->operation = $operation;
    return $this;
  }

  public function getOperation() {
    return $this->operation;
  }

  public function setBoxView(PHUIObjectBoxView $box_view) {
    $this->boxView = $box_view;
    return $this;
  }

  public function getBoxView() {
    return $this->boxView;
  }

  public function render() {
    $viewer = $this->getUser();
    $operation = $this->getOperation();

    $list = $this->renderUnderwayState();

    // If the operation is currently underway, refresh the status view.
    if ($operation->isUnderway()) {
      $status_id = celerity_generate_unique_node_id();
      $id = $operation->getID();

      $list->setID($status_id);

      Javelin::initBehavior(
        'drydock-live-operation-status',
        array(
          'statusID' => $status_id,
          'updateURI' => "/drydock/operation/{$id}/status/",
        ));
    }

    $box_view = $this->getBoxView();
    if (!$box_view) {
      $box_view = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Operation Status'));
    }
    $box_view->setObjectList($list);

    return $box_view;
  }

  public function renderUnderwayState() {
    $viewer = $this->getUser();
    $operation = $this->getOperation();

    $id = $operation->getID();

    $state = $operation->getOperationState();
    $icon = DrydockRepositoryOperation::getOperationStateIcon($state);
    $name = DrydockRepositoryOperation::getOperationStateName($state);

    $item = id(new PHUIObjectItemView())
      ->setHref("/drydock/operation/{$id}/")
      ->setHeader($operation->getOperationDescription($viewer))
      ->setStatusIcon($icon, $name);

    if ($state != DrydockRepositoryOperation::STATE_FAIL) {
      $item->addAttribute($operation->getOperationCurrentStatus($viewer));
    } else {
      // TODO: Make this more useful.
      $item->addAttribute(pht('Operation encountered an error.'));
    }

    return id(new PHUIObjectItemListView())
      ->addItem($item);
  }

}
