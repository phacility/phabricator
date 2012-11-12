<?php

/**
 * Listener for Maniphest Task edge events. When some workflow causes task
 * edges to be added or removed, we consider the edge edit authoritative but
 * duplicate the information into a ManiphestTansaction for display.
 *
 * @group maniphest
 */
final class ManiphestEdgeEventListener extends PhutilEventListener {

  private $edges = array();
  private $tasks = array();

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_EDGE_WILLEDITEDGES);
    $this->listen(PhabricatorEventType::TYPE_EDGE_DIDEDITEDGES);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_EDGE_WILLEDITEDGES:
        return $this->handleWillEditEvent($event);
      case PhabricatorEventType::TYPE_EDGE_DIDEDITEDGES:
        return $this->handleDidEditEvent($event);
    }
  }

  private function handleWillEditEvent(PhutilEvent $event) {
    // NOTE: Everything is namespaced by `id` so that we aren't left in an
    // inconsistent state if an edit fails to complete (e.g., something throws)
    // or an edit happens inside another edit.

    $id = $event->getValue('id');

    $edges = $this->loadAllEdges($event);
    $tasks = array();
    if ($edges) {
      $tasks = id(new ManiphestTask())->loadAllWhere(
        'phid IN (%Ls)',
        array_keys($edges));
      $tasks = mpull($tasks, null, 'getPHID');
    }

    $this->edges[$id] = $edges;
    $this->tasks[$id] = $tasks;
  }

  private function handleDidEditEvent(PhutilEvent $event) {
    $id = $event->getValue('id');

    $old_edges = $this->edges[$id];
    $tasks = $this->tasks[$id];

    unset($this->edges[$id]);
    unset($this->tasks[$id]);

    $new_edges = $this->loadAllEdges($event);
    $editor = new ManiphestTransactionEditor();
    $editor->setActor($event->getUser());

    foreach ($tasks as $phid => $task) {
      $xactions = array();

      $old = $old_edges[$phid];
      $new = $new_edges[$phid];

      $types = array_keys($old + $new);
      foreach ($types as $type) {
        $old_type = idx($old, $type, array());
        $new_type = idx($new, $type, array());

        if ($old_type === $new_type) {
          continue;
        }

        $xactions[] = id(new ManiphestTransaction())
          ->setTransactionType(ManiphestTransactionType::TYPE_EDGE)
          ->setOldValue($old_type)
          ->setNewValue($new_type)
          ->setMetadataValue('edge:type', $type)
          ->setAuthorPHID($event->getUser()->getPHID());
      }

      if ($xactions) {
        $editor->applyTransactions($task, $xactions);
      }
    }
  }

  private function filterEdgesBySourceType(array $edges, $type) {
    foreach ($edges as $key => $edge) {
      if ($edge['src_type'] !== $type) {
        unset($edges[$key]);
      }
    }
    return $edges;
  }

  private function loadAllEdges(PhutilEvent $event) {
    $add_edges = $event->getValue('add');
    $rem_edges = $event->getValue('rem');

    $type_task = PhabricatorPHIDConstants::PHID_TYPE_TASK;

    $all_edges = array_merge($add_edges, $rem_edges);
    $all_edges = $this->filterEdgesBySourceType($all_edges, $type_task);

    if (!$all_edges) {
      return;
    }

    $all_tasks = array();
    $all_types = array();
    foreach ($all_edges as $edge) {
      $all_tasks[$edge['src']] = true;
      $all_types[$edge['type']] = true;
    }

    $all_tasks = array_keys($all_tasks);
    $all_types = array_keys($all_types);

    return id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($all_tasks)
      ->withEdgeTypes($all_types)
      ->needEdgeData(true)
      ->execute();
  }

}
