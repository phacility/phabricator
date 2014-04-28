<?php

/**
 * @group slowvote
 */
final class PhabricatorSlowvoteListController
  extends PhabricatorSlowvoteController
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
      ->setSearchEngine(new PhabricatorSlowvoteSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $polls,
    PhabricatorSavedQuery $query) {
    assert_instances_of($polls, 'PhabricatorSlowvotePoll');
    $viewer = $this->getRequest()->getUser();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $phids = mpull($polls, 'getAuthorPHID');
    $handles = $this->loadViewerHandles($phids);

    foreach ($polls as $poll) {
      $date_created = phabricator_datetime($poll->getDateCreated(), $viewer);
      if ($poll->getAuthorPHID()) {
        $author = $handles[$poll->getAuthorPHID()]->renderLink();
      } else {
        $author = null;
      }

      $item = id(new PHUIObjectItemView())
        ->setObjectName('V'.$poll->getID())
        ->setHeader($poll->getQuestion())
        ->setHref('/V'.$poll->getID())
        ->setDisabled($poll->getIsClosed())
        ->addIcon('none', $date_created);

      $description = $poll->getDescription();
      if (strlen($description)) {
        $item->addAttribute(phutil_utf8_shorten($poll->getDescription(), 120));
      }

      if ($author) {
        $item->addByline(pht('Author: %s', $author));
      }

      $list->addItem($item);
    }

    return $list;
  }

}
