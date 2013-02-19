<?php

/**
 * @group slowvote
 */
final class PhabricatorSlowvoteListController
  extends PhabricatorSlowvoteController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view', parent::VIEW_ALL);
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $view = $this->view;
    $views = $this->getViews();

    $side_nav = $this->buildSideNavView($view);

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $pager->setURI($request->getRequestURI(), 'page');

    $polls = $this->loadPolls($pager, $view);

    $phids = mpull($polls, 'getAuthorPHID');
    $handles = $this->loadViewerHandles($phids);

    $rows = array();
    foreach ($polls as $poll) {
      $rows[] = array(
        'V'.$poll->getID(),
        phutil_tag(
          'a',
          array(
            'href' => '/V'.$poll->getID(),
          ),
          $poll->getQuestion()),
        $handles[$poll->getAuthorPHID()]->renderLink(),
        phabricator_date($poll->getDateCreated(), $user),
        phabricator_time($poll->getDateCreated(), $user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        '',
        'pri wide',
        '',
        '',
        'right',
      ));
    $table->setHeaders(
      array(
        pht('ID'),
        pht('Poll'),
        pht('Author'),
        pht('Date'),
        pht('Time'),
      ));

    switch ($view) {
      case self::VIEW_ALL:
        $table_header =
          pht('Slowvotes Not Yet Consumed by the Ravages of Time');
      break;
      case self::VIEW_CREATED:
        $table_header =
          pht('Slowvotes Birthed from Your Noblest of Great Minds');
      break;
      case self::VIEW_VOTED:
        $table_header =
          pht('Slowvotes Within Which You Express Your Mighty Opinion');
      break;
    }

    $panel = new AphrontPanelView();
    $panel->setHeader($table_header);
    $panel->setNoBackground();
    $panel->appendChild($table);
    $panel->appendChild($pager);

    $side_nav->appendChild($panel);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($views[$view])
        ->setHref($this->getApplicationURI()));
    $side_nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $side_nav,
      array(
        'title' => pht('Slowvotes'),
        'device' => true,
      ));
  }

  private function loadPolls(AphrontPagerView $pager, $view) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $poll = new PhabricatorSlowvotePoll();

    $conn = $poll->establishConnection('r');
    $offset = $pager->getOffset();
    $limit = $pager->getPageSize() + 1;

    switch ($view) {
      case self::VIEW_ALL:
        $data = queryfx_all(
          $conn,
          'SELECT * FROM %T ORDER BY id DESC LIMIT %d, %d',
          $poll->getTableName(),
          $offset,
          $limit);
        break;
      case self::VIEW_CREATED:
        $data = queryfx_all(
          $conn,
          'SELECT * FROM %T WHERE authorPHID = %s ORDER BY id DESC
            LIMIT %d, %d',
          $poll->getTableName(),
          $user->getPHID(),
          $offset,
          $limit);
        break;
      case self::VIEW_VOTED:
        $choice = new PhabricatorSlowvoteChoice();
        $data = queryfx_all(
          $conn,
          'SELECT p.* FROM %T p JOIN %T o
            ON o.pollID = p.id
            WHERE o.authorPHID = %s
            GROUP BY p.id
            ORDER BY p.id DESC
            LIMIT %d, %d',
          $poll->getTableName(),
          $choice->getTableName(),
          $user->getPHID(),
          $offset,
          $limit);
        break;
    }

    $data = $pager->sliceResults($data);
    return $poll->loadAllFromArray($data);
  }

}
