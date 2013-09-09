<?php

final class PonderQuestionListController extends PonderController
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
      ->setSearchEngine(new PonderQuestionSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $questions,
    PhabricatorSavedQuery $query) {
    assert_instances_of($questions, 'PonderQuestion');
    $viewer = $this->getRequest()->getUser();

    $phids = array();
    $phids[] = mpull($questions, 'getAuthorPHID');
    $phids = array_mergev($phids);

    $handles = $this->loadViewerHandles($phids);


    $view = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($questions as $question) {
      $item = new PHUIObjectItemView();
      $item->setObjectName('Q'.$question->getID());
      $item->setHeader($question->getTitle());
      $item->setHref('/Q'.$question->getID());
      $item->setObject($question);
      $item->setBarColor(
        PonderQuestionStatus::getQuestionStatusTagColor(
          $question->getStatus()));

      $created_date = phabricator_date($question->getDateCreated(), $viewer);
      $item->addIcon('none', $created_date);
      $item->addByline(
        pht(
          'Asked by %s',
          $handles[$question->getAuthorPHID()]->renderLink()));

      $item->addAttribute(
        pht('%d Answer(s)', $question->getAnswerCount()));

      $view->addItem($item);
    }

    return $view;
  }

}
