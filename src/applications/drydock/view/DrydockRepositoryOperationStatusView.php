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
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
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
      ->setObjectName(pht('Operation %d', $id))
      ->setHeader($operation->getOperationDescription($viewer))
      ->setStatusIcon($icon, $name);

    if ($state != DrydockRepositoryOperation::STATE_FAIL) {
      $item->addAttribute($operation->getOperationCurrentStatus($viewer));
    } else {
      $vcs_error = $operation->getCommandError();
      if ($vcs_error) {
        switch ($vcs_error['phase']) {
          case DrydockWorkingCopyBlueprintImplementation::PHASE_SQUASHMERGE:
            $message = pht(
              'This change did not merge cleanly. This usually indicates '.
              'that the change is out of date and needs to be updated.');
            break;
          case DrydockWorkingCopyBlueprintImplementation::PHASE_REMOTEFETCH:
            $message = pht(
              'This change could not be fetched from the remote.');
            break;
          case DrydockWorkingCopyBlueprintImplementation::PHASE_MERGEFETCH:
            $message = pht(
              'This change could not be fetched from the remote staging '.
              'area. It may not have been pushed, or may have been removed.');
            break;
          case DrydockLandRepositoryOperation::PHASE_COMMIT:
            $message = pht(
              'Committing this change failed. It may already have been '.
              'merged.');
            break;
          case DrydockLandRepositoryOperation::PHASE_PUSH:
            $message = pht(
              'The push failed. This usually indicates '.
              'that the change is breaking some rules or '.
              'some custom commit hook has failed.');
            break;
          default:
            $message = pht(
              'Operation encountered an error while performing repository '.
              'operations.');
            break;
        }

        $item->addAttribute($message);

        $table = $this->renderVCSErrorTable($vcs_error);
        list($links, $info) = $this->renderDetailToggles($table);

        $item->addAttribute($links);
        $item->appendChild($info);
      } else {
        $item->addAttribute(pht('Operation encountered an error.'));
      }

      $is_dismissed = $operation->getIsDismissed();

      $item->addAction(
        id(new PHUIListItemView())
          ->setName('Dismiss')
          ->setIcon('fa-times')
          ->setDisabled($is_dismissed)
          ->setWorkflow(true)
          ->setHref("/drydock/operation/{$id}/dismiss/"));
    }

    return id(new PHUIObjectItemListView())
      ->addItem($item);
  }

  private function renderVCSErrorTable(array $vcs_error) {
    $rows = array();

    $rows[] = array(
      pht('Command'),
      phutil_censor_credentials($vcs_error['command']),
    );

    $rows[] = array(pht('Error'), $vcs_error['err']);

    $rows[] = array(
      pht('Stdout'),
      phutil_censor_credentials($vcs_error['stdout']),
    );

    $rows[] = array(
      pht('Stderr'),
      phutil_censor_credentials($vcs_error['stderr']),
    );

    $table = id(new AphrontTableView($rows))
      ->setColumnClasses(
        array(
          'header',
          'wide prewrap',
        ));

    return $table;
  }

  private function renderDetailToggles(AphrontTableView $table) {
    $show_id = celerity_generate_unique_node_id();
    $hide_id = celerity_generate_unique_node_id();
    $info_id = celerity_generate_unique_node_id();

    Javelin::initBehavior('phabricator-reveal-content');

    $show_details = javelin_tag(
      'a',
      array(
        'id' => $show_id,
        'href' => '#',
        'sigil' => 'reveal-content',
        'mustcapture' => true,
        'meta' => array(
          'hideIDs' => array($show_id),
          'showIDs' => array($hide_id, $info_id),
        ),
      ),
      pht('Show Details'));

    $hide_details = javelin_tag(
      'a',
      array(
        'id' => $hide_id,
        'href' => '#',
        'sigil' => 'reveal-content',
        'mustcapture' => true,
        'style' => 'display: none',
        'meta' => array(
          'hideIDs' => array($hide_id, $info_id),
          'showIDs' => array($show_id),
        ),
      ),
      pht('Hide Details'));

    $info = javelin_tag(
      'div',
      array(
        'id' => $info_id,
        'style' => 'display: none',
      ),
      $table);

    $links = array(
      $show_details,
      $hide_details,
    );

    return array($links, $info);
  }

}
