<?php

final class PhabricatorProjectUIEventListener
  extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES);
  }

  public function handleEvent(PhutilEvent $event) {
    $object = $event->getValue('object');

    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES:
        // Hacky solution so that property list view on Diffusion
        // commits shows build status, but not Projects, Subscriptions,
        // or Tokens.
        if ($object instanceof PhabricatorRepositoryCommit) {
          return;
        }
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
      $engine = id(new PhabricatorBoardLayoutEngine())
        ->setViewer($user)
        ->setBoardPHIDs($project_phids)
        ->setObjectPHIDs(array($object->getPHID()))
        ->executeLayout();

      // TDOO: Generalize this UI and move it out of Maniphest.
      require_celerity_resource('maniphest-task-summary-css');

      foreach ($project_phids as $project_phid) {
        $handle = $handles[$project_phid];

        $columns = $engine->getObjectColumns(
          $project_phid,
          $object->getPHID());

        $annotation = array();
        foreach ($columns as $column) {
          $project_id = $column->getProject()->getID();

          $column_name = pht('(%s)', $column->getDisplayName());
          $column_link = phutil_tag(
            'a',
            array(
              'href' => $column->getWorkboardURI(),
              'class' => 'maniphest-board-link',
            ),
            $column_name);

          $annotation[] = $column_link;
        }

        if ($annotation) {
          $annotations[$project_phid] = array(
            ' ',
            phutil_implode_html(', ', $annotation),
          );
        }
      }

    }

    if ($handles) {
      $list = id(new PHUIHandleTagListView())
        ->setHandles($handles)
        ->setAnnotations($annotations)
        ->setShowHovercards(true);
    } else {
      $list = phutil_tag('em', array(), pht('None'));
    }

    $view = $event->getValue('view');
    $view->addProperty(pht('Projects'), $list);
  }

}
