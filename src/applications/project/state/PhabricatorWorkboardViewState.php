<?php

final class PhabricatorWorkboardViewState
  extends Phobject {

  private $viewer;
  private $project;
  private $requestState = array();
  private $savedQuery;
  private $searchEngine;
  private $layoutEngine;
  private $objects;

  public function setProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }

  public function getProject() {
    return $this->project;
  }

  public function readFromRequest(AphrontRequest $request) {
    if ($request->getExists('hidden')) {
      $this->requestState['hidden'] = $request->getBool('hidden');
    }

    if ($request->getExists('order')) {
      $this->requestState['order'] = $request->getStr('order');
    }

    // On some pathways, the search engine query key may be specified with
    // either a "?filter=X" query parameter or with a "/query/X/" URI
    // component. If both are present, the URI component is controlling.

    // In particular, the "queryKey" URI parameter is used by
    // "buildSavedQueryFromRequest()" when we are building custom board filters
    // by invoking SearchEngine code.

    if ($request->getExists('filter')) {
      $this->requestState['filter'] = $request->getStr('filter');
    }

    $query_key = $request->getURIData('queryKey');
    if ($query_key !== null && strlen($query_key)) {
      $this->requestState['filter'] = $request->getURIData('queryKey');
    }

    $this->viewer = $request->getViewer();

    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function getSavedQuery() {
    if ($this->savedQuery === null) {
      $this->savedQuery = $this->newSavedQuery();
    }

    return $this->savedQuery;
  }

  private function newSavedQuery() {
    $search_engine = $this->getSearchEngine();
    $query_key = $this->getQueryKey();
    $viewer = $this->getViewer();

    if ($search_engine->isBuiltinQuery($query_key)) {
      $saved_query = $search_engine->buildSavedQueryFromBuiltin($query_key);
    } else {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($viewer)
        ->withQueryKeys(array($query_key))
        ->executeOne();
    }

    return $saved_query;
  }

  public function getSearchEngine() {
    if ($this->searchEngine === null) {
      $this->searchEngine = $this->newSearchEngine();
    }

    return $this->searchEngine;
  }

  private function newSearchEngine() {
    $viewer = $this->getViewer();

    // TODO: This URI is not fully state-preserving, because "SearchEngine"
    // does not preserve URI parameters when constructing some URIs at time of
    // writing.
    $board_uri = $this->getProject()->getWorkboardURI();

    return id(new ManiphestTaskSearchEngine())
      ->setViewer($viewer)
      ->setBaseURI($board_uri)
      ->setIsBoardView(true);
  }

  public function newWorkboardURI($path = '') {
    $project = $this->getProject();
    $uri = urisprintf('%s%s', $project->getWorkboardURI(), $path);
    return $this->newURI($uri);
  }

  public function newURI($path) {
    $project = $this->getProject();
    $uri = new PhutilURI($path);

    $request_order = $this->getOrder();
    $default_order = $this->getDefaultOrder();
    if ($request_order !== $default_order) {
      $request_value = idx($this->requestState, 'order');
      if ($request_value !== null) {
        $uri->replaceQueryParam('order', $request_value);
      } else {
        $uri->removeQueryParam('order');
      }
    } else {
      $uri->removeQueryParam('order');
    }

    $request_query = $this->getQueryKey();
    $default_query = $this->getDefaultQueryKey();
    if ($request_query !== $default_query) {
      $request_value = idx($this->requestState, 'filter');
      if ($request_value !== null) {
        $uri->replaceQueryParam('filter', $request_value);
      } else {
        $uri->removeQueryParam('filter');
      }
    } else {
      $uri->removeQueryParam('filter');
    }

    if ($this->getShowHidden()) {
      $uri->replaceQueryParam('hidden', 'true');
    } else {
      $uri->removeQueryParam('hidden');
    }

    return $uri;
  }

  public function getShowHidden() {
    $request_show = idx($this->requestState, 'hidden');

    if ($request_show !== null) {
      return $request_show;
    }

    return false;
  }

  public function getOrder() {
    $request_order = idx($this->requestState, 'order');
    if ($request_order !== null) {
      if ($this->isValidOrder($request_order)) {
        return $request_order;
      }
    }

    return $this->getDefaultOrder();
  }

  public function getQueryKey() {
    $request_query = idx($this->requestState, 'filter');
    if ($request_query !== null && strlen($request_query)) {
      return $request_query;
    }

    return $this->getDefaultQueryKey();
  }

  public function setQueryKey($query_key) {
    $this->requestState['filter'] = $query_key;
    return $this;
  }

  private function isValidOrder($order) {
    $map = PhabricatorProjectColumnOrder::getEnabledOrders();
    return isset($map[$order]);
  }

  private function getDefaultOrder() {
    $project = $this->getProject();

    $default_order = $project->getDefaultWorkboardSort();

    if ($this->isValidOrder($default_order)) {
      return $default_order;
    }

    return PhabricatorProjectColumnNaturalOrder::ORDERKEY;
  }

  private function getDefaultQueryKey() {
    $project = $this->getProject();

    $default_query = $project->getDefaultWorkboardFilter();

    if ($default_query !== null && strlen($default_query)) {
      return $default_query;
    }

    return 'open';
  }

  public function getQueryParameters() {
    return $this->requestState;
  }

  public function getLayoutEngine() {
    if ($this->layoutEngine === null) {
      $this->layoutEngine = $this->newLayoutEngine();
    }
    return $this->layoutEngine;
  }

  private function newLayoutEngine() {
    $project = $this->getProject();
    $viewer = $this->getViewer();

    $board_phid = $project->getPHID();
    $objects = $this->getObjects();

    // Regardless of display order, pass tasks to the layout engine in ID order
    // so layout is consistent.
    $objects = msort($objects, 'getID');

    $layout_engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($viewer)
      ->setObjectPHIDs(array_keys($objects))
      ->setBoardPHIDs(array($board_phid))
      ->setFetchAllBoards(true)
      ->executeLayout();

    return $layout_engine;
  }

  public function getBoardContainerPHIDs() {
    $project = $this->getProject();
    $viewer = $this->getViewer();

    $container_phids = array($project->getPHID());
    if ($project->getHasSubprojects() || $project->getHasMilestones()) {
      $descendants = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withAncestorProjectPHIDs($container_phids)
        ->execute();
      foreach ($descendants as $descendant) {
        $container_phids[] = $descendant->getPHID();
      }
    }

    return $container_phids;
  }

  public function getObjects() {
    if ($this->objects === null) {
      $this->objects = $this->newObjects();
    }

    return $this->objects;
  }

  private function newObjects() {
    $viewer = $this->getViewer();
    $saved_query = $this->getSavedQuery();
    $search_engine = $this->getSearchEngine();

    $container_phids = $this->getBoardContainerPHIDs();

    $task_query = $search_engine->buildQueryFromSavedQuery($saved_query)
      ->setViewer($viewer)
      ->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_ANCESTOR,
        array($container_phids));

    $tasks = $task_query->execute();
    $tasks = mpull($tasks, null, 'getPHID');

    return $tasks;
  }

}
