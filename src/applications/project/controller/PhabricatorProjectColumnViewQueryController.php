<?php

final class PhabricatorProjectColumnViewQueryController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $state = $this->getViewState();
    $board_uri = $state->newWorkboardURI();

    // NOTE: We're performing layout without handing the "LayoutEngine" any
    // object PHIDs. We only want to get access to the column object the user
    // is trying to query, so we do not need to actually position any cards on
    // the board.

    $board_phid = $project->getPHID();

    $layout_engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs(array($board_phid))
      ->setFetchAllBoards(true)
      ->executeLayout();

    $columns = $layout_engine->getColumns($board_phid);
    $columns = mpull($columns, null, 'getID');

    $column_id = $request->getURIData('columnID');
    $column = idx($columns, $column_id);
    if (!$column) {
      return new Aphront404Response();
    }

    // Create a saved query to combine the active filter on the workboard
    // with the column filter. If the user currently has constraints on the
    // board, we want to add a new column or project constraint, not
    // completely replace the constraints.
    $default_query = $state->getSavedQuery();
    $saved_query = $default_query->newCopy();

    if ($column->getProxyPHID()) {
      $project_phids = $saved_query->getParameter('projectPHIDs');
      if (!$project_phids) {
        $project_phids = array();
      }
      $project_phids[] = $column->getProxyPHID();
      $saved_query->setParameter('projectPHIDs', $project_phids);
    } else {
      $saved_query->setParameter(
        'columnPHIDs',
        array($column->getPHID()));
    }

    $search_engine = id(new ManiphestTaskSearchEngine())
      ->setViewer($viewer);

    $search_engine->saveQuery($saved_query);

    $query_key = $saved_query->getQueryKey();
    $query_uri = new PhutilURI("/maniphest/query/{$query_key}/#R");

    return id(new AphrontRedirectResponse())
      ->setURI($query_uri);
  }

}
