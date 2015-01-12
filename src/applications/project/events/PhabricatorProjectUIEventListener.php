<?php

final class PhabricatorProjectUIEventListener
  extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES:
        $this->handlePropertyEvent($event);
        break;
    }
  }

  private function handlePropertyEvent($event) {
    $user = $event->getUser();
    $object = $event->getValue('object');

    if (!$object || !$object->getPHID()) {
      // No object, or the object has no PHID yet..
      return;
    }

    if (!($object instanceof PhabricatorProjectInterface)) {
      // This object doesn't have projects.
      return;
    }

    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
    if ($project_phids) {
      $project_phids = array_reverse($project_phids);
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($user)
        ->withPHIDs($project_phids)
        ->execute();
    } else {
      $handles = array();
    }

    // If this object can appear on boards, build the workboard annotations.
    // Some day, this might be a generic interface. For now, only tasks can
    // appear on boards.
    $can_appear_on_boards = ($object instanceof ManiphestTask);

    $annotations = array();
    if ($handles && $can_appear_on_boards) {

      // TDOO: Generalize this UI and move it out of Maniphest.

      require_celerity_resource('maniphest-task-summary-css');

      $positions_query = id(new PhabricatorProjectColumnPositionQuery())
        ->setViewer($user)
        ->withBoardPHIDs($project_phids)
        ->withObjectPHIDs(array($object->getPHID()))
        ->needColumns(true);

      // This is important because positions will be created "on demand"
      // based on the set of columns. If we don't specify it, positions
      // won't be created.
      $columns = id(new PhabricatorProjectColumnQuery())
        ->setViewer($user)
        ->withProjectPHIDs($project_phids)
        ->execute();
      if ($columns) {
        $positions_query->withColumns($columns);
      }
      $positions = $positions_query->execute();
      $positions = mpull($positions, null, 'getBoardPHID');

      foreach ($project_phids as $project_phid) {
        $handle = $handles[$project_phid];

        $position = idx($positions, $project_phid);
        if ($position) {
          $column = $position->getColumn();

          $column_name = pht('(%s)', $column->getDisplayName());
          $column_link = phutil_tag(
            'a',
            array(
              'href' => $handle->getURI().'board/',
              'class' => 'maniphest-board-link',
            ),
            $column_name);

          $annotations[$project_phid] = array(
            ' ',
            $column_link,
          );
        }
      }

    }

    if ($handles) {
      $list = id(new PHUIHandleTagListView())
        ->setHandles($handles)
        ->setAnnotations($annotations);
    } else {
      $list = phutil_tag('em', array(), pht('None'));
    }

    $view = $event->getValue('view');
    $view->addProperty(pht('Projects'), $list);
  }

}
