<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorSlowvoteListController
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

    $filters = array(
      self::VIEW_ALL      => 'All Slowvotes',
      self::VIEW_CREATED  => 'Created',
      self::VIEW_VOTED    => 'Voted In',
    );

    $view = isset($filters[$this->view])
      ? $this->view
      : self::VIEW_ALL;

    $side_nav = new AphrontSideNavView();
    foreach ($filters as $key => $name) {
      $side_nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/vote/view/'.$key.'/',
            'class' => ($view == $key) ? 'aphront-side-nav-selected' : null,
          ),
          phutil_escape_html($name)));
    }


    $poll = new PhabricatorSlowvotePoll();
    $conn = $poll->establishConnection('r');

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $pager->setURI($request->getRequestURI(), 'page');
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
    $polls = $poll->loadAllFromArray($data);

    $phids = mpull($polls, 'getAuthorPHID');
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $rows = array();
    foreach ($polls as $poll) {
      $rows[] = array(
        $handles[$poll->getAuthorPHID()]->renderLink(),
        phutil_render_tag(
          'a',
          array(
            'href' => '/V'.$poll->getID(),
          ),
          phutil_escape_html('V'.$poll->getID().' '.$poll->getQuestion())),
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
        'right',
      ));
    $table->setHeaders(
      array(
        'Author',
        'Poll',
        'Date',
        'Time',
      ));

    $headers = array(
      self::VIEW_ALL
        => 'Slowvotes Not Yet Consumed by the Ravages of Time',
      self::VIEW_CREATED
        => 'Slowvotes Birthed from Your Noblest of Great Minds',
      self::VIEW_VOTED
        => 'Slowvotes Within Which You Express Your Mighty Opinion',
    );

    $panel = new AphrontPanelView();
    $panel->setHeader(idx($headers, $view));
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

}
