<?php

final class PhabricatorProjectColumnBulkEditController
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

    $layout_engine = $state->getLayoutEngine();

    $board_phid = $project->getPHID();
    $columns = $layout_engine->getColumns($board_phid);
    $columns = mpull($columns, null, 'getID');

    $column_id = $request->getURIData('columnID');
    $bulk_column = idx($columns, $column_id);
    if (!$bulk_column) {
      return new Aphront404Response();
    }

    $bulk_task_phids = $layout_engine->getColumnObjectPHIDs(
      $board_phid,
      $bulk_column->getPHID());

    $tasks = $state->getObjects();

    $bulk_tasks = array_select_keys($tasks, $bulk_task_phids);

    $bulk_tasks = id(new PhabricatorPolicyFilter())
      ->setViewer($viewer)
      ->requireCapabilities(array(PhabricatorPolicyCapability::CAN_EDIT))
      ->apply($bulk_tasks);

    if (!$bulk_tasks) {
      return $this->newDialog()
        ->setTitle(pht('No Editable Tasks'))
        ->appendParagraph(
          pht(
            'The selected column contains no visible tasks which you '.
            'have permission to edit.'))
        ->addCancelButton($board_uri);
    }

    // Create a saved query to hold the working set. This allows us to get
    // around URI length limitations with a long "?ids=..." query string.
    // For details, see T10268.
    $search_engine = id(new ManiphestTaskSearchEngine())
      ->setViewer($viewer);

    $saved_query = $search_engine->newSavedQuery();
    $saved_query->setParameter('ids', mpull($bulk_tasks, 'getID'));
    $search_engine->saveQuery($saved_query);

    $query_key = $saved_query->getQueryKey();

    $bulk_uri = new PhutilURI("/maniphest/bulk/query/{$query_key}/");
    $bulk_uri->replaceQueryParam('board', $project->getID());

    return id(new AphrontRedirectResponse())
      ->setURI($bulk_uri);
  }

}
