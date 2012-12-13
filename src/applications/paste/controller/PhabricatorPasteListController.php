<?php

final class PhabricatorPasteListController extends PhabricatorPasteController {

  public function shouldRequireLogin() {
    return false;
  }

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new PhabricatorPasteQuery();
    $query->setViewer($user);

    $nav = $this->buildSideNavView($this->filter);
    $filter = $nav->getSelectedFilter();

    switch ($filter) {
      case 'my':
        $query->withAuthorPHIDs(array($user->getPHID()));
        $title = pht('My Pastes');
        $nodata = pht("You haven't created any Pastes yet.");
        break;
      case 'all':
        $title = pht('All Pastes');
        $nodata = pht("There are no Pastes yet.");
        break;
    }

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);
    $pastes = $query->executeWithCursorPager($pager);

    $list = $this->buildPasteList($pastes);
    $list->setPager($pager);
    $list->setNoDataString($nodata);

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $nav->appendChild(
      array(
        $header,
        $list,
      ));

    $crumbs = $this
      ->buildApplicationCrumbs($nav)
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($title)
          ->setHref($this->getApplicationURI('filter/'.$filter.'/')));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      )
    );
  }

  private function buildPasteList(array $pastes) {
    assert_instances_of($pastes, 'PhabricatorPaste');

    $user = $this->getRequest()->getUser();

    $this->loadHandles(mpull($pastes, 'getAuthorPHID'));

    $list = new PhabricatorObjectItemListView();
    $list->setViewer($user);
    foreach ($pastes as $paste) {
      $created = phabricator_date($paste->getDateCreated(), $user);
      $author = $this->getHandle($paste->getAuthorPHID())->renderLink();

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($paste->getFullName())
        ->setHref('/P'.$paste->getID())
        ->setObject($paste)
        ->addAttribute(pht('Created %s by %s', $created, $author));
      $list->addItem($item);
    }

    return $list;
  }

}
