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

class DifferentialRevisionListController extends DifferentialController {

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $phid_arr = $request->getArr('view_user');
      $view_target = head($phid_arr);
      return id(new AphrontRedirectResponse())
        ->setURI($request->getRequestURI()->alter('phid', $view_target));
    }

    $filters = array(
      'User Revisions',
      'active' => array(
        'name'  => 'Active Revisions',
        'queries' => array(
          array(
            'query'
              => DifferentialRevisionListData::QUERY_NEED_ACTION_FROM_SELF,
            'header' => 'Action Required',
            'nodata' => 'You have no revisions requiring action.',
          ),
          array(
            'query'
              => DifferentialRevisionListData::QUERY_NEED_ACTION_FROM_OTHERS,
            'header' => 'Waiting on Others',
            'nodata' => 'You have no revisions waiting on others',
          ),
        ),
      ),
      'open' => array(
        'name' => 'Open Revisions',
        'queries' => array(
          array(
            'query' => DifferentialRevisionListData::QUERY_OPEN_OWNED,
            'header' => 'Your Open Revisions',
          ),
        ),
      ),
      'reviews' => array(
        'name' => 'Open Reviews',
        'queries' => array(
          array(
            'query' => DifferentialRevisionListData::QUERY_OPEN_REVIEWER,
            'header' => 'Your Open Reviews',
          ),
        ),
      ),
      'all' => array(
        'name' => 'All Revisions',
        'queries' => array(
          array(
            'query' => DifferentialRevisionListData::QUERY_OWNED,
            'header' => 'Your Revisions',
          ),
        ),
      ),
      'related' => array(
        'name' => 'All Revisions and Reviews',
        'queries' => array(
          array(
            'query' => DifferentialRevisionListData::QUERY_OWNED_OR_REVIEWER,
            'header' => 'Your Revisions and Reviews',
          ),
        ),
      ),
      'updates' => array(
        'name' => 'Updates',
        'queries' => array(
          array(
            'query' => DifferentialRevisionListData::QUERY_UPDATED_SINCE,
            'header' =>
              'Diffs that have been updated since you\'ve last viewed them',
          ),
        ),
      ),
      '<hr />',
      'All Revisions',
      'allopen' => array(
        'name' => 'Open',
        'nofilter' => true,
        'queries' => array(
          array(
            'query' => DifferentialRevisionListData::QUERY_ALL_OPEN,
            'header' => 'All Open Revisions',
          ),
        ),
      ),
    );

    if (empty($filters[$this->filter])) {
      $this->filter = 'active';
    }

    $view_phid = nonempty($request->getStr('phid'), $user->getPHID());

    $queries = array();
    $filter = $filters[$this->filter];
    foreach ($filter['queries'] as $query) {
      $query_object = new DifferentialRevisionListData(
        $query['query'],
        array($view_phid));
      $queries[] = array(
        'object' => $query_object,
      ) + $query;
    }

    $side_nav = new AphrontSideNavView();

    $query = null;
    if ($view_phid) {
      $query = '?phid='.$view_phid;
    }

    foreach ($filters as $filter_name => $filter_desc) {
      if (is_int($filter_name)) {
        $side_nav->addNavItem(
          phutil_render_tag(
            'span',
            array(),
            $filter_desc));
        continue;
      }
      $selected = ($filter_name == $this->filter);
      $side_nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/differential/filter/'.$filter_name.'/'.$query,
            'class' => $selected ? 'aphront-side-nav-selected' : null,
          ),
          phutil_escape_html($filter_desc['name'])));
    }

    $phids = array();

    $phids[$view_phid] = true;

    $rev_ids = array();
    foreach ($queries as $key => $query) {
      $revisions = $query['object']->loadRevisions();
      foreach ($revisions as $revision) {
        $phids[$revision->getAuthorPHID()] = true;
        $rev_ids[$revision->getID()] = true;
      }
      $queries[$key]['revisions'] = $revisions;
    }

    $rev = new DifferentialRevision();
    if ($rev_ids) {
      $rev_ids = array_keys($rev_ids);
      $reviewers = queryfx_all(
        $rev->establishConnection('r'),
        'SELECT revisionID, objectPHID FROM %T revision JOIN %T relationship
          ON revision.id = relationship.revisionID
          WHERE revision.id IN (%Ld)
            AND relationship.relation = %s
          ORDER BY sequence',
        $rev->getTableName(),
        DifferentialRevision::RELATIONSHIP_TABLE,
        $rev_ids,
        DifferentialRevision::RELATION_REVIEWER);

      $reviewer_map = array();
      foreach ($reviewers as $reviewer) {
        $reviewer_map[$reviewer['revisionID']][] = $reviewer['objectPHID'];
      }
      foreach ($reviewer_map as $revision_id => $reviewer_ids) {
        $phids[reset($reviewer_ids)] = true;
      }
    } else {
      $reviewer_map = array();
    }

    if ($phids) {
      $phids = array_keys($phids);
      $handles = id(new PhabricatorObjectHandleData($phids))
        ->loadHandles();
    } else {
      $handles = array();
    }

    if (empty($filters[$this->filter]['nofilter'])) {
      $filter_form = id(new AphrontFormView())
        ->setUser($user)
        ->appendChild(
          id(new AphrontFormTokenizerControl())
            ->setDatasource('/typeahead/common/users/')
            ->setLabel('View User')
            ->setName('view_user')
            ->setValue(
              array(
                $view_phid => $handles[$view_phid]->getFullName(),
              ))
            ->setLimit(1));

      $filter_view = new AphrontListFilterView();
      $filter_view->appendChild($filter_form);
      $side_nav->appendChild($filter_view);
    }

    foreach ($queries as $query) {
      $table = $this->renderRevisionTable(
        $query['revisions'],
        $query['header'],
        idx($query, 'nodata'),
        $handles,
        $reviewer_map);
      $side_nav->appendChild($table);
    }

    return $this->buildStandardPageResponse(
      $side_nav,
      array(
        'title' => 'Differential Home',
        'tab' => 'revisions',
      ));
  }

  private function renderRevisionTable(
    array $revisions,
    $header,
    $nodata,
    array $handles,
    array $reviewer_map) {

    $rows = array();
    foreach ($revisions as $revision) {
      $status = DifferentialRevisionStatus::getNameForRevisionStatus(
        $revision->getStatus());

      $reviewers = idx($reviewer_map, $revision->getID(), array());
      if ($reviewers) {
        $first = reset($reviewers);
        if (count($reviewers) > 1) {
          $suffix = ' (+'.(count($reviewers) - 1).')';
        } else {
          $suffix = null;
        }
        $reviewers = $handles[$first]->renderLink().$suffix;
      } else {
        $reviewers = '<em>None</em>';
      }

      $rows[] = array(
        'D'.$revision->getID(),
        '<strong>'.phutil_render_tag(
          'a',
          array(
            'href' => '/D'.$revision->getID(),
          ),
          phutil_escape_html($revision->getTitle())).'</strong>',
        phutil_escape_html($status),
        number_format($revision->getLineCount()),
        $handles[$revision->getAuthorPHID()]->renderLink(),
        $reviewers,
        phabricator_format_timestamp($revision->getDateModified()),
        phabricator_format_timestamp($revision->getDateCreated()),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Revision',
        'Status',
        'Lines',
        'Author',
        'Reviewers',
        'Updated',
        'Created',
      ));
    $table->setColumnClasses(
      array(
        null,
        'wide',
        null,
        null,
        null,
        null,
        null,
        null,
      ));
    if ($nodata !== null) {
      $table->setNoDataString($nodata);
    }


    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->appendChild($table);

    return $panel;
  }

}
