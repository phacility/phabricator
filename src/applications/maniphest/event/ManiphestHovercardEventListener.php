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
    $e_dep_on = PhabricatorEdgeConfig::TYPE_TASK_DEPENDS_ON_TASK;
    $e_dep_by = PhabricatorEdgeConfig::TYPE_TASK_DEPENDED_ON_BY_TASK;

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

    $phids = array_filter(array_merge(
      array($owner_phid),
      $edge_phids));

    $viewer_handles = $this->loadHandles($phids, $viewer);

    $hovercard->setTitle(pht('T%d', $task->getID()))
      ->setDetail($task->getTitle());

    $owner = phutil_tag('em', array(), pht('None'));
    if ($owner_phid) {
      $owner = $viewer_handles[$owner_phid]->renderLink();
    }

    $hovercard->addField(pht('Assigned to'), $owner);

    if ($edge_phids) {
      $edge_types = array(
        $e_project => pht('Projects'),
        $e_dep_by => pht('Dependent Tasks'),
        $e_dep_on  => pht('Depends On'),
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
            implode_selected_handle_links(', ', $viewer_handles,
              array_keys($edges[$edge_type]))
                ->appendHTML($edge_overflow));
        }
      }
    }

    $hovercard->addTag(ManiphestView::renderTagForTask($task));

    $event->setValue('hovercard', $hovercard);
  }

  protected function loadHandles(array $phids, $viewer) {
    return id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
  }

}
