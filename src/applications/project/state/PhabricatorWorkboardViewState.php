<?php

final class PhabricatorWorkboardViewState
  extends Phobject {

  private $project;
  private $requestState = array();

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

    if (strlen($request->getURIData('queryKey'))) {
      $this->requestState['filter'] = $request->getURIData('queryKey');
    }

    return $this;
  }

  public function newWorkboardURI($path = null) {
    $project = $this->getProject();
    $uri = urisprintf('%p%p', $project->getWorkboardURI(), $path);
    return $this->newURI($uri);
  }

  public function newURI($path, $force = false) {
    $project = $this->getProject();
    $uri = new PhutilURI($path);

    $request_order = $this->getOrder();
    $default_order = $this->getDefaultOrder();
    if ($force || ($request_order !== $default_order)) {
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
    if ($force || ($request_query !== $default_query)) {
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
    if (strlen($request_query)) {
      return $request_query;
    }

    return $this->getDefaultQueryKey();
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

    if (strlen($default_query)) {
      return $default_query;
    }

    return 'open';
  }

  public function getQueryParameters() {
    return $this->requestState;
  }

}
