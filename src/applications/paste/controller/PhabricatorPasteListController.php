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

    $nav = $this->buildSideNavView();
    $filter = $nav->selectFilter($this->filter, 'my');

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
    $list->setHeader($title);
    $list->setPager($pager);
    $list->setNoDataString($nodata);

    $nav->appendChild($list);

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
    foreach ($pastes as $paste) {
      $created = phabricator_datetime($paste->getDateCreated(), $user);

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($paste->getFullName())
        ->setHref('/P'.$paste->getID())
        ->addDetail(
          pht('Author'),
          $this->getHandle($paste->getAuthorPHID())->renderLink())
        ->addAttribute(pht('Created %s', $created));

      $list->addItem($item);
    }

    return $list;
  }

}
