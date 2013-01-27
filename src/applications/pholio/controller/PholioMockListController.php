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

    $query = id(new PholioMockQuery())
      ->setViewer($user)
      ->needCoverFiles(true);

    $nav = $this->buildSideNav();
    $filter = $nav->selectFilter('view/'.$this->view, 'view/all');

    switch ($filter) {
      case 'view/all':
      default:
        $title = 'All Mocks';
        break;
    }

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $mocks = $query->executeWithCursorPager($pager);

    $board = new PhabricatorPinboardView();
    foreach ($mocks as $mock) {
      $board->addItem(
        id(new PhabricatorPinboardItemView())
          ->setHeader($mock->getName())
          ->setURI('/M'.$mock->getID())
          ->setImageURI($mock->getCoverFile()->getThumb160x120URI()));
    }

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $content = array(
      $header,
      $board,
      $pager,
    );

    $nav->appendChild($content);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

}
