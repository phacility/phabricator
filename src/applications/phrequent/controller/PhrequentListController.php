<?php

final class PhrequentListController extends PhrequentController
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
      ->setSearchEngine(new PhrequentSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $usertimes,
    PhabricatorSavedQuery $query) {
    assert_instances_of($usertimes, 'PhrequentUserTime');
    $viewer = $this->getRequest()->getUser();

    $phids = array();
    $phids[] = mpull($usertimes, 'getUserPHID');
    $phids[] = mpull($usertimes, 'getObjectPHID');
    $phids = array_mergev($phids);

    $handles = $this->loadViewerHandles($phids);

    $view = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($usertimes as $usertime) {
      $item = new PHUIObjectItemView();

      if ($usertime->getObjectPHID() === null) {
        $item->setHeader($usertime->getNote());
      } else {
        $obj = $handles[$usertime->getObjectPHID()];
        $item->setHeader($obj->getLinkName());
        $item->setHref($obj->getURI());
      }
      $item->setObject($usertime);

      $item->addByline(
        pht(
          'Tracked: %s',
          $handles[$usertime->getUserPHID()]->renderLink()));

      $started_date = phabricator_date($usertime->getDateStarted(), $viewer);
      $item->addIcon('none', $started_date);

      if ($usertime->getDateEnded() !== null) {
        $time_spent = $usertime->getDateEnded() - $usertime->getDateStarted();
        $time_ended = phabricator_datetime($usertime->getDateEnded(), $viewer);
      } else {
        $time_spent = time() - $usertime->getDateStarted();
      }

      $time_spent = $time_spent == 0 ? 'none' :
        phabricator_format_relative_time_detailed($time_spent);

      if ($usertime->getDateEnded() !== null) {
        $item->addAttribute(
          pht(
            'Tracked %s',
            $time_spent));
        $item->addAttribute(
          pht(
            'Ended on %s',
            $time_ended));
      } else {
        $item->addAttribute(
          pht(
            'Tracked %s so far',
            $time_spent));
        if ($usertime->getObjectPHID() !== null &&
            $usertime->getUserPHID() === $viewer->getPHID()) {
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('history')
              ->addSigil('phrequent-stop-tracking')
              ->setWorkflow(true)
              ->setRenderNameAsTooltip(true)
              ->setName(pht("Stop"))
              ->setHref(
                '/phrequent/track/stop/'.
                $usertime->getObjectPHID().'/'));
        }
        $item->setBarColor('green');
      }

      $view->addItem($item);
    }

    return $view;
  }

}
