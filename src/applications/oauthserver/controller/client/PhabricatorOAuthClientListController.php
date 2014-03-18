<?php

final class PhabricatorOAuthClientListController
  extends PhabricatorOAuthClientBaseController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

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
      ->setSearchEngine(new PhabricatorOAuthServerClientSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $clients,
    PhabricatorSavedQuery $query) {
    assert_instances_of($clients, 'PhabricatorOauthServerClient');

    $viewer = $this->getRequest()->getUser();
    $this->loadHandles(mpull($clients, 'getCreatorPHID'));

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($clients as $client) {
      $creator = $this->getHandle($client->getCreatorPHID());

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Application %d', $client->getID()))
        ->setHeader($client->getName())
        ->setHref($client->getViewURI())
        ->setObject($client)
        ->addByline(pht('Creator: %s', $creator->renderLink()));

      $list->addItem($item);
    }

    return $list;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setHref($this->getApplicationURI('client/create/'))
        ->setName(pht('Create Application'))
        ->setIcon('create'));

    return $crumbs;
  }

}
