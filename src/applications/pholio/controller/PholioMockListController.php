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
      ->needCoverFiles(true)
      ->needImages(true)
      ->needTokenCounts(true);

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

    $author_phids = array();
    foreach ($mocks as $mock) {
      $author_phids[] = $mock->getAuthorPHID();
    }
    $this->loadHandles($author_phids);


    $board = new PhabricatorPinboardView();
    foreach ($mocks as $mock) {
      $item = new PhabricatorPinboardItemView();
      $item->setHeader('M'.$mock->getID().' '.$mock->getName())
           ->setURI('/M'.$mock->getID())
           ->setImageURI($mock->getCoverFile()->getThumb280x210URI())
           ->setImageSize(280, 210)
           ->addIconCount('image', count($mock->getImages()))
           ->addIconCount('like', $mock->getTokenCount());

      if ($mock->getAuthorPHID()) {
        $author_handle = $this->getHandle($mock->getAuthorPHID());
        $datetime = phabricator_date($mock->getDateCreated(), $user);
        $item->appendChild(
          pht('By %s on %s', $author_handle->renderLink(), $datetime));
      }
      $board->addItem($item);
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
        'dust' => true,
      ));
  }

}
