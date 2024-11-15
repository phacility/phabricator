<?php

final class PhabricatorRoleWorkboardViewState
  extends Phobject {

  private $viewer;
  private $role;
  private $requestState = array();
  private $savedQuery;
  private $searchEngine;
  private $layoutEngine;
  private $objects;

  public function setRole(PhabricatorRole $role) {
    $this->role = $role;
    return $this;
  }

  public function getRole() {
    return $this->role;
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
    if ($query_key !== null && $query_key !== '') {
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
    $board_uri = $this->getRole()->getWorkboardURI();

    return id(new ManiphestTaskSearchEngine())
      ->setViewer($viewer)
      ->setBaseURI($board_uri)
      ->setIsBoardView(true);
  }

  public function newWorkboardURI($path = null) {
    $role = $this->getRole();
    $uri = urisprintf('%s%s', $role->getWorkboardURI(), $path);
    return $this->newURI($uri);
  }

  public function newURI($path) {
    $role = $this->getRole();
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
    if ($request_query !== null && $request_query !== '') {
      return $request_query;
    }

    return $this->getDefaultQueryKey();
  }

  public function setQueryKey($query_key) {
    $this->requestState['filter'] = $query_key;
    return $this;
  }

  private function isValidOrder($order) {
    $map = PhabricatorRoleColumnOrder::getEnabledOrders();
    return isset($map[$order]);
  }

  private function getDefaultOrder() {
    $role = $this->getRole();

    $default_order = $role->getDefaultWorkboardSort();

    if ($this->isValidOrder($default_order)) {
      return $default_order;
    }

    return PhabricatorRoleColumnNaturalOrder::ORDERKEY;
  }

  private function getDefaultQueryKey() {
    $role = $this->getRole();

    $default_query = $role->getDefaultWorkboardFilter();

    if ($default_query !== null && $default_query !== '') {
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
    $role = $this->getRole();
    $viewer = $this->getViewer();

    $board_phid = $role->getPHID();
    $objects = $this->getObjects();

    // Regardless of display order, pass tasks to the layout engine in ID order
    // so layout is consistent.
    $objects = msort($objects, 'getID');

    $layout_engine = id(new PhabricatorRoleBoardLayoutEngine())
      ->setViewer($viewer)
      ->setObjectPHIDs(array_keys($objects))
      ->setBoardPHIDs(array($board_phid))
      ->setFetchAllBoards(true)
      ->executeLayout();

    return $layout_engine;
  }

  public function getBoardContainerPHIDs() {
    $role = $this->getRole();
    $viewer = $this->getViewer();

    $container_phids = array($role->getPHID());
    if ($role->getHasSubroles() || $role->getHasMilestones()) {
      $descendants = id(new PhabricatorRoleQuery())
        ->setViewer($viewer)
        ->withAncestorRolePHIDs($container_phids)
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
        PhabricatorRoleObjectHasRoleEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_ANCESTOR,
        array($container_phids));

    $tasks = $task_query->execute();
    $tasks = mpull($tasks, null, 'getPHID');

    return $tasks;
  }

}
