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

class DiffusionCommitListController extends DiffusionController {

  private $username;

  public function willProcessRequest(array $data) {
    if (isset($data['username'])) {
      $this->username = $data['username'];
    }
  }

  public function processRequest() {
    $request = $this->getRequest();
    if ($this->username) {
      $user = id(new PhabricatorUser())->loadOneWhere(
        'username = %s',
        $this->username);
    } else {
      $user = $request->getUser();
    }

    $content = array();
    if (!$user) {
      $error_view = new AphrontErrorView();
      $error_view->setSeverity(AphrontErrorView::SEVERITY_ERROR);
      $error_body = 'User name '.
        '<b>'.phutil_escape_html($this->username).'</b>'.
        ' doesn\'t exist.';

      $error_view->setTitle("Error");
      $error_view->appendChild('<p>'.$error_body.'</p>');

      $content[] = $error_view;
    } else {

      $pager = new AphrontPagerView();
      $pager->setOffset($request->getInt('offset'));
      $pager->setURI($request->getRequestURI(), 'offset');

      $query = new PhabricatorSearchQuery();
      $query->setParameter('type', PhabricatorPHIDConstants::PHID_TYPE_CMIT);
      $query->setParameter('author', array($user->getPHID()));
      $query->setParameter('limit', $pager->getPageSize() + 1);
      $query->setParameter('offset', $pager->getOffset());

      $user_link = phutil_render_tag(
        'a',
        array(
          'href' => '/p/'.$user->getUsername().'/',
        ),
        phutil_escape_html($user->getUsername()));

      $engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();
      $results = $engine->executeSearch($query);
      $results = $pager->sliceResults($results);
      $result_phids = ipull($results, 'phid');
      $commit_table = self::createCommitTable($result_phids, $user);

      $list_panel = new AphrontPanelView();
      $list_panel->setHeader('Commits by '.$user_link);
      $list_panel->appendChild($commit_table);
      $list_panel->appendChild($pager);

      $content[] = $list_panel;
    }

    return $this->buildStandardPageResponse(
      $content,
      array(
       'title' => 'Commit List',
      ));
  }


  /**
   * @param array $commit_phids phids of the commits to render
   * @param PhabricatorUser $user phabricator user
   * @return AphrontTableView
   */
  private static function createCommitTable(
    array $commit_phids, PhabricatorUser $user) {

    $loader = new PhabricatorObjectHandleData($commit_phids);
    $handles = $loader->loadHandles();
    $objects = $loader->loadObjects();

    $rows = array();
    foreach ($commit_phids as $phid) {
      $handle = $handles[$phid];
      $object = $objects[$phid];

      $summary = null;
      if ($object) {
        $commit_data = $object->getCommitData();
        if ($commit_data) {
          $summary = $commit_data->getSummary();
        }
      }

      $epoch = $handle->getTimeStamp();
      $date = phabricator_date($epoch, $user);
      $time = phabricator_time($epoch, $user);
      $link = phutil_render_tag(
        'a',
        array(
          'href' => $handle->getURI(),
        ),
        phutil_escape_html($handle->getName()));
      $rows[] = array(
        $link,
        $date,
        $time,
        phutil_escape_html($summary),
      );
    }
    $commit_table = new AphrontTableView($rows);
    $commit_table->setHeaders(
      array(
        'Commit',
        'Date',
        'Time',
        'Summary',
      ));
    $commit_table->setColumnClasses(
      array(
        '',
        '',
        'right',
        'wide',
      ));

    return $commit_table;
  }

}
