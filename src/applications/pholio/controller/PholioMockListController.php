<?php

final class PholioMockListController
  extends PholioController
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
      ->setSearchEngine(new PholioMockSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $mocks,
    PhabricatorSavedQuery $query) {
    assert_instances_of($mocks, 'PholioMock');

    $viewer = $this->getRequest()->getUser();

    $author_phids = array();
    foreach ($mocks as $mock) {
      $author_phids[] = $mock->getAuthorPHID();
    }
    $this->loadHandles($author_phids);

    $board = new PhabricatorPinboardView();
    foreach ($mocks as $mock) {
      $item = id(new PhabricatorPinboardItemView())
        ->setHeader('M'.$mock->getID().' '.$mock->getName())
        ->setURI('/M'.$mock->getID())
        ->setImageURI($mock->getCoverFile()->getThumb280x210URI())
        ->setImageSize(280, 210)
        ->addIconCount('image', count($mock->getImages()))
        ->addIconCount('like', $mock->getTokenCount());

      if ($mock->getAuthorPHID()) {
        $author_handle = $this->getHandle($mock->getAuthorPHID());
        $datetime = phabricator_date($mock->getDateCreated(), $viewer);
        $item->appendChild(
          pht('By %s on %s', $author_handle->renderLink(), $datetime));
      }

      $board->addItem($item);
    }

    return $board;
  }

}
