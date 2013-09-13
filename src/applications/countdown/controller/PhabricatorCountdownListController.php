<?php

final class PhabricatorCountdownListController
  extends PhabricatorCountdownController
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
      ->setSearchEngine(new PhabricatorCountdownSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $countdowns,
    PhabricatorSavedQuery $query) {
    assert_instances_of($countdowns, 'PhabricatorCountdown');

    $viewer = $this->getRequest()->getUser();

    $this->loadHandles(mpull($countdowns, 'getAuthorPHID'));


    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($countdowns as $countdown) {
      $id = $countdown->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName("C{$id}")
        ->setHeader($countdown->getTitle())
        ->setHref($this->getApplicationURI("{$id}/"))
        ->addByline(
          pht(
            'Created by %s',
            $this->getHandle($countdown->getAuthorPHID())->renderLink()));

      $epoch = $countdown->getEpoch();
      if ($epoch >= time()) {
        $item->addIcon(
          'none',
          pht('Ends %s', phabricator_datetime($epoch, $viewer)));
      } else {
        $item->addIcon(
          'delete',
          pht('Ended %s', phabricator_datetime($epoch, $viewer)));
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    return $list;
  }

}
