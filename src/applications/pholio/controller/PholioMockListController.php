<?php

/**
 * @group pholio
 */
final class PholioMockListController extends PholioController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $viewer_phid = $user->getPHID();

    $query = id(new PholioMockQuery())
      ->setViewer($user)
      ->needCoverFiles(true);

    $nav = $this->buildSideNav();
    $filter = $nav->selectFilter('view/'.$this->view, 'view/all');

    switch ($filter) {
      case 'view/all':
      default:
        $title = pht('All Mocks');
      break;
      case 'view/my':
        $title = pht('My Mocks');
        $query->withAuthorPHIDs(array($viewer_phid));
      break;
    }

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $mocks = $query->executeWithCursorPager($pager);

    $board = new PhabricatorPinboardView();
    foreach ($mocks as $mock) {
      $board->addItem(
        id(new PhabricatorPinboardItemView())
          ->setHeader('M'.$mock->getID().' '.$mock->getName())
          ->setURI('/M'.$mock->getID())
          ->setImageURI($mock->getCoverFile()->getThumb220x165URI())
          ->setImageSize(220, 165));
    }

    $content = array(
      $board,
      $pager,
    );

    $nav->appendChild($content);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNav());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($this->getApplicationURI()));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
