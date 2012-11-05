<?php

/**
 * @group slowvote
 */
final class PhabricatorSlowvoteListController
  extends PhabricatorSlowvoteController {

  private $view;

  const VIEW_ALL      = 'all';
  const VIEW_CREATED  = 'created';
  const VIEW_VOTED    = 'voted';

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $views = array(
      self::VIEW_ALL      => 'All Slowvotes',
      self::VIEW_CREATED  => 'Created',
      self::VIEW_VOTED    => 'Voted In',
    );

    $view = isset($views[$this->view])
      ? $this->view
      : self::VIEW_ALL;

    $side_nav = $this->renderSideNav($views, $view);

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
        phutil_render_tag(
          'a',
          array(
            'href' => '/V'.$poll->getID(),
          ),
          phutil_escape_html($poll->getQuestion())),
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
        'ID',
        'Poll',
        'Author',
        'Date',
        'Time',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader($this->getTableHeader($view));
    $panel->setCreateButton('Create Slowvote', '/vote/create/');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    $side_nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $side_nav,
      array(
        'title' => 'Slowvotes',
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

  private function renderSideNav(array $views, $view) {
    $side_nav = new AphrontSideNavView();
    foreach ($views as $key => $name) {
      $side_nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/vote/view/'.$key.'/',
            'class' => ($view == $key)
              ? 'aphront-side-nav-selected'
              : null,
          ),
          phutil_escape_html($name)));
    }
    return $side_nav;
  }

  private function getTableHeader($view) {
    static $headers = array(
      self::VIEW_ALL
        => 'Slowvotes Not Yet Consumed by the Ravages of Time',
      self::VIEW_CREATED
        => 'Slowvotes Birthed from Your Noblest of Great Minds',
      self::VIEW_VOTED
        => 'Slowvotes Within Which You Express Your Mighty Opinion',
    );
    return idx($headers, $view);
  }

}
