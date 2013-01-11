<?php

final class PhabricatorFeedMainController extends PhabricatorFeedController {

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNavView();
    $filter = $nav->selectFilter($this->filter, 'all');

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);
    $pager->setPageSize(200);


    $query = id(new PhabricatorFeedQuery())
      ->setViewer($user);

    $nodata = null;
    switch ($filter) {
      case 'all':
        $title = pht('Feed');
        break;
      case 'projects':
        $projects = id(new PhabricatorProjectQuery())
          ->setViewer($user)
          ->withMemberPHIDs(array($user->getPHID()))
          ->execute();

        if (!$projects) {
          $nodata = pht('You have not joined any projects.');
        } else {
          $query->setFilterPHIDs(mpull($projects, 'getPHID'));
        }

        $title = pht('Feed: My Projects');
        break;
    }

    if ($nodata) {
      $feed_view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NODATA)
        ->setTitle(pht('No Stories'))
        ->appendChild($nodata);
    } else {
      $feed = $query->executeWithCursorPager($pager);

      $builder = new PhabricatorFeedBuilder($feed);
      $builder->setUser($user);
      $feed_view = $builder->buildView();
    }

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $nav->appendChild(
      array(
        $header,
        $feed_view,
        $pager,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
