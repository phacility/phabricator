<?php

final class ManiphestTaskGraphController
  extends ManiphestController {

  public function shouldAllowPublic() {
    return true;
  }

  private function buildGraph(ManiphestTask $task, int $subtask_type) {
    switch ($subtask_type) {
      case ManiphestTaskDependsOnTaskEdgeType::EDGECONST:
        $parent_type = ManiphestTaskDependedOnByTaskEdgeType::EDGECONST;
        break;
      
      case ManiphestTaskBlockerEdgeType::EDGECONST:
        $parent_type = ManiphestTaskBlockedEdgeType::EDGECONST;
        break;

      default:
        return null;
    }

    $viewer = $this->getViewer();
    $graph_limit = 2000;
    $overflow_message = null;
    $task_graph = id(new ManiphestTaskGraph())
      ->setViewer($viewer)
      ->setSubtaskType($subtask_type)
      ->setSeedPHID($task->getPHID())
      ->setLimit($graph_limit)
      ->setIsStandalone(true)
      ->loadGraph();

    $graph_table = null;
    $has_parents = null;
    $has_subtasks = null;

    if (!$task_graph->isEmpty()) {
      $parent_map = $task_graph->getEdges($parent_type);
      $subtask_map = $task_graph->getEdges($subtask_type);
      $parent_list = idx($parent_map, $task->getPHID(), array());
      $subtask_list = idx($subtask_map, $task->getPHID(), array());
      $has_parents = (bool)$parent_list;
      $has_subtasks = (bool)$subtask_list;

      // First, get a count of direct parent tasks and subtasks. If there
      // are too many of these, we just don't draw anything. You can use
      // the search button to browse tasks with the search UI instead.
      $direct_count = count($parent_list) + count($subtask_list);

      if ($direct_count > $graph_limit) {
        $overflow_message = pht(
          'This task is directly connected to more than %s other tasks, '.
          'which is too many tasks to display. Use %s to browse parents '.
          'or subtasks.',
          new PhutilNumber($graph_limit),
          phutil_tag('strong', array(), pht('Search...')));
      } else {
        // If there aren't too many direct tasks, but there are too many total
        // tasks, we'll only render directly connected tasks.
        if ($task_graph->isOverLimit()) {
          $task_graph->setRenderOnlyAdjacentNodes(true);

          $overflow_message = pht(
            'This task is connected to more than %s other tasks. '.
            'Only direct parents and subtasks are shown here.',
            new PhutilNumber($graph_limit));
        }

        $graph_table = $task_graph->newGraphTable();
      }

      if ($overflow_message) {
        $overflow_view = $this->newTaskGraphOverflowView(
          $task,
          $overflow_message,
          false);
  
        $graph_table = array(
          $overflow_view,
          $graph_table,
        );
      }

    } else {
      $graph_table = null;

      $overflow_message = pht(
        'This task has no parent tasks and no subtasks, so there is no '.
        'graph to draw.');
    }

    $result = array(
      'table' => $graph_table,
      'has_parents' => $has_parents,
      'has_subtasks' => $has_subtasks,
    );
    return $result;

  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($task->getMonogram(), $task->getURI())
      ->addTextCrumb(pht('Graph'))
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Task Graph'));

    $subtask_graph_map = $this->buildGraph($task, ManiphestTaskDependsOnTaskEdgeType::EDGECONST);
    $subtask_graph_table = $subtask_graph_map['table'];
    $has_parents = $subtask_graph_map['has_parents'];
    $has_subtasks = $subtask_graph_map['has_subtasks'];

    $blocker_graph_map = $this->buildGraph($task, ManiphestTaskBlockerEdgeType::EDGECONST);
    $blocker_graph_table = $blocker_graph_map['table'];
    $has_blocked_tasks = $blocker_graph_map['has_parents'];
    $has_blockers = $blocker_graph_map['has_subtasks'];

    $graph_menu = $this->newTaskGraphDropdownMenu(
      $task,
      $has_parents,
      $has_blocked_tasks,
      $has_subtasks,
      $has_blockers,
      true);

    if ($graph_menu) {
      $header->addActionLink($graph_menu);
    }

    $subtask_header = id(new PHUIHeaderView())
      ->setHeader(pht('Subtasks'));

    $blocker_header = id(new PHUIHeaderView())
      ->setHeader(pht('Blockers'));

    $tab_view = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($subtask_header)
      ->appendChild($subtask_graph_table)
      ->appendChild($blocker_header)
      ->appendChild($blocker_graph_table);

    $view = id(new PHUITwoColumnView())
      ->setFooter($tab_view);

    return $this->newPage()
      ->setTitle(
        array(
          $task->getMonogram(),
          pht('Graph'),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }


}
