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

class PhabricatorOwnerRelatedListController
   extends PhabricatorOwnersController {

  private $request;
  private $user;
  private $view;
  private $packagePHID;
  private $auditStatus;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view', 'all');
  }

  public function processRequest() {
    $this->request = $this->getRequest();

    if ($this->request->isFormPost()) {
      $package_phids = $this->request->getArr('search_packages');
      $package_phid = head($package_phids);
      $status = $this->request->getStr('search_status');
      return id(new AphrontRedirectResponse())
        ->setURI(
          $this->request
            ->getRequestURI()
            ->alter('phid', $package_phid)
            ->alter('status', $status));
    }

    $this->user = $this->request->getUser();
    $this->packagePHID = nonempty($this->request->getStr('phid'), null);
    $this->auditStatus = $this->request->getStr('status', 'needaudit');

    $search_view = $this->renderSearchView();
    $list_panel = $this->renderListPanel();
    $nav = $this->renderSideNav();

    $nav->appendChild($search_view);
    $nav->appendChild($list_panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Related Commits',
        'tab' => 'related',
      ));
  }

  private function renderListPanel() {
    if (!$this->packagePHID) {
       return id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle('No package seleted. Please select one from above.');
    }

    $package = id(new PhabricatorOwnersPackage())->loadOneWhere(
      "phid = %s",
      $this->packagePHID);
    if ($this->view === 'audit' && !$package->getAuditingEnabled()) {
      return id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle("Package doesn't have auditing enabled. ".
                   "Please choose another one.");
    }

    $conn_r = id(new PhabricatorOwnersPackageCommitRelationship())
      ->establishConnection('r');
    $status_arr = $this->getStatusArr();

    $offset = $this->request->getInt('offset', 0);
    $pager = new AphrontPagerView();
    $pager->setPageSize(50);
    $pager->setOffset($offset);
    $pager->setURI($this->request->getRequestURI(), 'offset');

    $data = queryfx_all(
      $conn_r,
      'SELECT commitPHID, auditStatus, auditReasons FROM %T
        WHERE packagePHID = %s AND auditStatus in (%Ls)
        ORDER BY id DESC
        LIMIT %d, %d',
      id(new PhabricatorOwnersPackageCommitRelationship())->getTableName(),
      $package->getPHID(),
      $status_arr,
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $data = $pager->sliceResults($data);
    $data = ipull($data, null, 'commitPHID');

    $list_panel = $this->renderCommitTable($data, $package);
    $list_panel->appendChild($pager);

    return $list_panel;
  }

  private function getStatusArr() {
    switch ($this->view) {
      case 'all':
        $status_arr =
          array_keys(PhabricatorAuditStatusConstants::getStatusNameMap());
        break;
      case 'audit':
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

  private function renderSideNav() {
    $views = array(
      'all' => 'Related to Package',
      'audit' => 'Needs Attention',
    );

    $query = null;
    if ($this->packagePHID) {
      $query = '?phid=' . $this->packagePHID;
    }

    $nav = new AphrontSideNavView();
    foreach ($views as $key => $name) {
      $nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/owners/related/view/'.$key.'/'.$query,
            'class' => ($this->view === $key
               ? 'aphront-side-nav-selected'
               : null),
          ),
          phutil_escape_html($name)));
    }
    return $nav;
  }

  private function renderSearchView() {
    if ($this->packagePHID) {
      $loader = new PhabricatorObjectHandleData(array($this->packagePHID));
      $handles = $loader->loadHandles();
      $package_handle = $handles[$this->packagePHID];

      $view_packages = array(
        $this->packagePHID => $package_handle->getFullName(),
      );
    } else {
      $view_packages = array();
    }

    $search_form = id(new AphrontFormView())
      ->setUser($this->user)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/packages/')
          ->setLabel('Package')
          ->setName('search_packages')
          ->setValue($view_packages)
          ->setLimit(1));

    if ($this->view === 'audit') {
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

  private function renderCommitTable($data, PhabricatorOwnersPackage $package) {
    $commit_phids = array_keys($data);
    $loader = new PhabricatorObjectHandleData($commit_phids);
    $handles = $loader->loadHandles();
    $objects = $loader->loadObjects();

    $owners = id(new PhabricatorOwnersOwner())->loadAllWhere(
      'packageID = %d',
      $package->getID());
    $owners_phids = mpull($owners, 'getUserPHID');
    if ($this->user->getIsAdmin() ||
        in_array($this->user->getPHID(), $owners_phids)) {
      $allowed_to_audit = true;
    } else {
      $allowed_to_audit = false;
    }

    $rows = array();
    foreach ($commit_phids as $commit_phid) {
      $handle = $handles[$commit_phid];
      $object = $objects[$commit_phid];
      $commit_data = $object->getCommitData();
      $epoch = $handle->getTimeStamp();
      $date = phabricator_date($epoch, $this->user);
      $time = phabricator_time($epoch, $this->user);
      $link = phutil_render_tag(
        'a',
        array(
          'href' => $handle->getURI(),
        ),
        phutil_escape_html($handle->getName()));
      $row = array(
        $link,
        $date,
        $time,
        phutil_escape_html($commit_data->getSummary()),
      );

      if ($this->view === 'audit') {
        $relationship = $data[$commit_phid];
        $status_link = phutil_escape_html(
          idx(PhabricatorAuditStatusConstants::getStatusNameMap(),
            $relationship['auditStatus']));
        if ($allowed_to_audit)
          $status_link = phutil_render_tag(
            'a',
            array(
              'href' => sprintf('/audit/edit/?c-phid=%s&p-phid=%s',
                idx($relationship, 'commitPHID'),
                $this->packagePHID),
            ),
            $status_link);

        $reasons = json_decode($relationship['auditReasons'], true);
        $reasons = array_map('phutil_escape_html', $reasons);
        $reasons = implode($reasons, '<br>');

        $row = array_merge(
          $row,
          array(
            $status_link,
            $reasons,
          ));
      }

      $rows[] = $row;
    }

    $commit_table = new AphrontTableView($rows);

    $headers = array(
      'Commit',
      'Date',
      'Time',
      'Summary',
    );
    if ($this->view === 'audit') {
      $headers = array_merge(
        $headers,
        array(
          'Audit Status',
          'Audit Reasons',
        ));
    }
    $commit_table->setHeaders($headers);

    $column_classes =
      array(
        '',
        '',
        'right',
        'wide',
      );
    if ($this->view === 'audit') {
      $column_classes = array_merge(
        $column_classes,
        array(
          '',
          '',
        ));
    }
    $commit_table->setColumnClasses($column_classes);

    $list_panel = new AphrontPanelView();
    $list_panel->setHeader('Commits Related to package "'.
      phutil_render_tag(
        'a',
        array(
          'href' => '/owners/package/'.$package->getID().'/',
        ),
        phutil_escape_html($package->getName())).
      '"'.
      ($this->view === 'audit' ? ' and need attention' : ''));
    $list_panel->appendChild($commit_table);

    return $list_panel;
  }
}
