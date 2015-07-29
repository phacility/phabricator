<?php

final class ManiphestHovercardEventListener extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERHOVERCARD);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERHOVERCARD:
        $this->handleHovercardEvent($event);
      break;
    }
  }

  private function handleHovercardEvent(PhutilEvent $event) {
    $viewer = $event->getUser();
    $hovercard = $event->getValue('hovercard');
    $handle = $event->getValue('handle');
    $phid = $handle->getPHID();
    $task = $event->getValue('object');

    if (!($task instanceof ManiphestTask)) {
      return;
    }

    $e_project = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
    // Fun with "Unbeta Pholio", hua hua
    $e_dep_on = ManiphestTaskDependsOnTaskEdgeType::EDGECONST;
    $e_dep_by = ManiphestTaskDependedOnByTaskEdgeType::EDGECONST;

    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($phid))
      ->withEdgeTypes(
        array(
          $e_project,
          $e_dep_on,
          $e_dep_by,
        ));
    $edges = idx($edge_query->execute(), $phid);
    $edge_phids = $edge_query->getDestinationPHIDs();

    $owner_phid = $task->getOwnerPHID();

    $hovercard
      ->setTitle(pht('T%d', $task->getID()))
      ->setDetail($task->getTitle());

    if ($owner_phid) {
      $owner = $viewer->renderHandle($owner_phid);
    } else {
      $owner = phutil_tag('em', array(), pht('None'));
    }
    $hovercard->addField(pht('Assigned To'), $owner);
    $hovercard->addField(
      pht('Priority'),
      ManiphestTaskPriority::getTaskPriorityName($task->getPriority()));

    if ($edge_phids) {
      $edge_types = array(
        $e_project => pht('Projects'),
        $e_dep_by => pht('Blocks'),
        $e_dep_on  => pht('Blocked By'),
      );

      $max_count = 6;
      foreach ($edge_types as $edge_type => $edge_name) {
        if ($edges[$edge_type]) {
          // TODO: This can be made more sophisticated. We still load all
          // edges into memory. Only load the ones we need.
          $edge_overflow = array();
          if (count($edges[$edge_type]) > $max_count) {
            $edges[$edge_type] = array_slice($edges[$edge_type], 0, 6, true);
            $edge_overflow = ', ...';
          }

          $hovercard->addField(
            $edge_name,
            array(
              $viewer->renderHandleList(array_keys($edges[$edge_type])),
              $edge_overflow,
            ));
        }
      }
    }

    $hovercard->addTag(ManiphestView::renderTagForTask($task));

    $event->setValue('hovercard', $hovercard);
  }

}
