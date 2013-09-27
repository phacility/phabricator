<?php

final class DifferentialRevisionListController extends DifferentialController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldRequireLogin() {
    if ($this->allowsAnonymousAccess()) {
      return false;
    }
    return parent::shouldRequireLogin();
  }

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new DifferentialRevisionSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $revisions,
    PhabricatorSavedQuery $query) {
    assert_instances_of($revisions, 'DifferentialRevision');

    $user = $this->getRequest()->getUser();
    $template = id(new DifferentialRevisionListView())
      ->setUser($user)
      ->setFields(DifferentialRevisionListView::getDefaultFields($user));

    $views = array();
    if ($query->getQueryKey() == 'active') {
        $split = DifferentialRevisionQuery::splitResponsible(
          $revisions,
          $query->getParameter('responsiblePHIDs'));
        list($blocking, $active, $waiting) = $split;

      $views[] = id(clone $template)
        ->setHeader(pht('Blocking Others'))
        ->setNoDataString(
          pht('No revisions are blocked on your action.'))
        ->setHighlightAge(true)
        ->setRevisions($blocking)
        ->setHandles(array())
        ->loadAssets();

      $views[] = id(clone $template)
        ->setHeader(pht('Action Required'))
        ->setNoDataString(
          pht('No revisions require your action.'))
        ->setHighlightAge(true)
        ->setRevisions($active)
        ->setHandles(array())
        ->loadAssets();

      $views[] = id(clone $template)
        ->setHeader(pht('Waiting on Others'))
        ->setNoDataString(
          pht('You have no revisions waiting on others.'))
        ->setRevisions($waiting)
        ->setHandles(array())
        ->loadAssets();
    } else {
      $views[] = id(clone $template)
        ->setRevisions($revisions)
        ->setHandles(array())
        ->loadAssets();
    }

    $phids = array_mergev(mpull($views, 'getRequiredHandlePHIDs'));
    $handles = $this->loadViewerHandles($phids);

    foreach ($views as $view) {
      $view->setHandles($handles);
    }

    if (count($views) == 1) {
      // Reduce this to a PHUIObjectItemListView so we can get the free
      // support from ApplicationSearch.
      return head($views)->render();
    } else {
      return $views;
    }
  }

}
