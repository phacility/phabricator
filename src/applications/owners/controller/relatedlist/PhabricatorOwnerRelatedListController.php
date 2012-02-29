<?php

/*
 * Copyright 2012 Facebook, Inc.
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

class PhabricatorOwnerRelatedListController
   extends PhabricatorOwnersController {

  private $request;
  private $user;
  private $scope;
  private $view;
  private $searchPHID;
  private $auditStatus;

  public function willProcessRequest(array $data) {
    $this->scope = idx($data, 'scope', 'related');
    $this->view = idx($data, 'view', 'owner');
  }

  public function processRequest() {
    $this->request = $this->getRequest();

    if ($this->request->isFormPost()) {
      $phids = $this->request->getArr('search_phids');
      $phid = head($phids);
      $status = $this->request->getStr('search_status');
      return id(new AphrontRedirectResponse())
        ->setURI(
          $this->request
            ->getRequestURI()
            ->alter('phid', $phid)
            ->alter('status', $status));
    }

    $this->user = $this->request->getUser();
    $this->searchPHID = nonempty($this->request->getStr('phid'), null);
    if ($this->view === 'owner' && !$this->searchPHID) {
      $this->searchPHID = $this->user->getPHID();
    }
    $this->auditStatus = $this->request->getStr('status', 'needaudit');

    $search_view = $this->renderSearchView();
    $list_panel = $this->renderListPanel();

    $side_nav_filter = $this->scope.'/'.$this->view;
    $this->setSideNavFilter($side_nav_filter);

    return $this->buildStandardPageResponse(
      array(
        $search_view,
        $list_panel,
      ),
      array(
        'title' => 'Related Commits',
      ));
  }

  protected function getRelatedViews() {
    $related_views = parent::getRelatedViews();
    if ($this->searchPHID) {
      $query = $this->getQueryString();
      foreach ($related_views as &$view) {
        // Pass on the query string to the filter item with the same view.
        if (preg_match('/'.preg_quote($this->view, '/').'$/', $view['key'])) {
          $view['uri'] = $view['key'].$query;
          $view['relative'] = true;
        }
      }
    }
    return $related_views;
  }

  protected function getAttentionViews() {
    $related_views = parent::getAttentionViews();
    if ($this->searchPHID) {
      $query = $this->getQueryString();
      foreach ($related_views as &$view) {
        // Pass on the query string to the filter item with the same view.
        if (preg_match('/'.preg_quote($this->view, '/').'$/', $view['key'])) {
          $view['uri'] = $view['key'].$query;
          $view['relative'] = true;
        }
      }
    }
    return $related_views;
  }

  private function getQueryString() {
    $query = null;
    if ($this->searchPHID) {
      $query = '/?phid='.$this->searchPHID;
    }
    return $query;
  }

  private function renderListPanel() {
    if (!$this->searchPHID) {
       return id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle('No search specified. Please add one from above.');
    }

    $package = new PhabricatorOwnersPackage();
    $owner = new PhabricatorOwnersOwner();
    $relationship = id(new PhabricatorOwnersPackageCommitRelationship());

    switch ($this->view) {
      case 'package':
        $package = $package->loadOneWhere(
          "phid = %s",
          $this->searchPHID);
        if ($this->scope === 'attention' && !$package->getAuditingEnabled()) {
          return id(new AphrontErrorView())
            ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
            ->setTitle("Package doesn't have auditing enabled. ".
                       "Please choose another one.");
        }
        $packages = array($package);
        break;
      case 'owner':
        $data = queryfx_all(
          $package->establishConnection('r'),
          'SELECT p.* FROM %T p JOIN %T o ON p.id = o.packageID
            WHERE o.userPHID = %s GROUP BY p.id',
          $package->getTableName(),
          $owner->getTableName(),
          $this->searchPHID);
        $packages = $package->loadAllFromArray($data);
        break;
      default:
        throw new Exception('view of the page not recognized.');
    }

    $status_arr = $this->getStatusArr();

    $offset = $this->request->getInt('offset', 0);
    $pager = new AphrontPagerView();
    $pager->setPageSize(50);
    $pager->setOffset($offset);
    $pager->setURI($this->request->getRequestURI(), 'offset');

    $packages = mpull($packages, null, 'getPHID');
    if ($packages) {
      $data = queryfx_all(
        $relationship->establishConnection('r'),
        'SELECT commitPHID, packagePHID, auditStatus, auditReasons FROM %T
          WHERE packagePHID in (%Ls) AND auditStatus in (%Ls)
          ORDER BY id DESC
          LIMIT %d, %d',
        $relationship->getTableName(),
        array_keys($packages),
        $status_arr,
        $pager->getOffset(),
        $pager->getPageSize() + 1);
    } else {
      $data = array();
    }

    $data = $pager->sliceResults($data);
    $data = ipull($data, null, 'commitPHID');

    $list_panel = $this->renderCommitTable($data, $packages);
    $list_panel->appendChild($pager);

    return $list_panel;
  }

  private function getStatusArr() {
    switch ($this->scope) {
      case 'related':
        $status_arr =
          array_keys(PhabricatorAuditStatusConstants::getStatusNameMap());
        break;
      case 'attention':
        switch ($this->auditStatus) {
          case 'needaudit':
            $status_arr =
              array(
                PhabricatorAuditStatusConstants::AUDIT_REQUIRED,
                PhabricatorAuditStatusConstants::CONCERNED,
              );
            break;
          case 'accepted':
            $status_arr =
              array(
                PhabricatorAuditStatusConstants::ACCEPTED,
              );
            break;
          case 'all':
            $status_arr =
              array(
                PhabricatorAuditStatusConstants::AUDIT_REQUIRED,
                PhabricatorAuditStatusConstants::CONCERNED,
                PhabricatorAuditStatusConstants::ACCEPTED,
              );
            break;
          default:
            throw new Exception("Status {$this->auditStatus} not recognized");
        }
        break;

      default:
        throw new Exception("view {$this->view} not recognized");
    }
    return $status_arr;
  }

  private function renderSearchView() {
    if ($this->searchPHID) {
      $loader = new PhabricatorObjectHandleData(array($this->searchPHID));
      $handles = $loader->loadHandles();
      $handle = $handles[$this->searchPHID];

      $view_items = array(
        $this->searchPHID => $handle->getFullName(),
      );
    } else {
      $view_items = array();
    }

    switch ($this->view) {
      case 'package':
        $datasource = '/typeahead/common/packages/';
        $label = 'Package';
        break;
      case 'owner':
        $datasource = '/typeahead/common/users/';
        $label = 'Owner';
        break;
      default:
        throw new Exception('view of the page not recognized.');
    }

    $search_form = id(new AphrontFormView())
      ->setUser($this->user)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource($datasource)
          ->setLabel($label)
          ->setName('search_phids')
          ->setValue($view_items)
          ->setLimit(1));

    if ($this->scope === 'attention') {
      $select_map = array(
        'needaudit' => 'Needs Audit',
        'accepted' => 'Accepted',
        'all' => 'All',
      );
      $select = id(new AphrontFormSelectControl())
        ->setLabel('Audit Status')
        ->setName('search_status')
        ->setOptions($select_map)
        ->setValue($this->auditStatus);

      $search_form->appendChild($select);
    }

    $search_form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue('Search'));

    $search_view = new AphrontListFilterView();
    $search_view->appendChild($search_form);
    return $search_view;
  }

  private function renderCommitTable($data, array $packages) {
    $commit_phids = array_keys($data);
    $loader = new PhabricatorObjectHandleData($commit_phids);
    $handles = $loader->loadHandles();
    $objects = $loader->loadObjects();

    $rows = array();
    foreach ($commit_phids as $commit_phid) {
      $handle = $handles[$commit_phid];
      $object = $objects[$commit_phid];
      $commit_data = $object->getCommitData();
      $relationship = $data[$commit_phid];
      $package_phid = $relationship['packagePHID'];
      $package = $packages[$package_phid];

      $epoch = $handle->getTimeStamp();
      $date = phabricator_date($epoch, $this->user);
      $time = phabricator_time($epoch, $this->user);
      $commit_link = phutil_render_tag(
        'a',
        array(
          'href' => $handle->getURI(),
        ),
        phutil_escape_html($handle->getName()));

      $package_link = phutil_render_tag(
        'a',
        array(
          'href' => '/owners/package/'.$package->getID().'/',
        ),
        phutil_escape_html($package->getName()));

      $row = array(
        $commit_link,
        $package_link,
        $date,
        $time,
        phutil_escape_html($commit_data->getSummary()),
      );

      if ($this->scope === 'attention') {
        $reasons = json_decode($relationship['auditReasons'], true);
        $reasons = array_map('phutil_escape_html', $reasons);
        $reasons = implode($reasons, '<br>');

        $row = array_merge(
          $row,
          array(
            $reasons,
          ));
      }

      $rows[] = $row;
    }

    $commit_table = new AphrontTableView($rows);

    $headers = array(
      'Commit',
      'Package',
      'Date',
      'Time',
      'Summary',
    );
    if ($this->scope === 'attention') {
      $headers = array_merge(
        $headers,
        array(
          'Audit Reasons',
        ));
    }
    $commit_table->setHeaders($headers);

    $column_classes =
      array(
        '',
        '',
        '',
        'right',
        'wide',
      );
    if ($this->scope === 'attention') {
      $column_classes = array_merge(
        $column_classes,
        array(
          '',
        ));
    }
    $commit_table->setColumnClasses($column_classes);

    $list_panel = new AphrontPanelView();
    $list_panel->appendChild($commit_table);

    return $list_panel;
  }
}
