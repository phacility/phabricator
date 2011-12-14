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

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view', 'all');
  }

  public function processRequest() {
    $this->request = $this->getRequest();

    if ($this->request->isFormPost()) {
      $package_phids = $this->request->getArr('search_packages');
      $package_phid = head($package_phids);
      return id(new AphrontRedirectResponse())
        ->setURI(
          $this->request
            ->getRequestURI()
            ->alter('phid', $package_phid));
    }

    $this->user = $this->request->getUser();
    $this->packagePHID = nonempty($this->request->getStr('phid'), null);

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

    $offset = $this->request->getInt('offset', 0);
    $pager = new AphrontPagerView();
    $pager->setPageSize(50);
    $pager->setOffset($offset);
    $pager->setURI($this->request->getRequestURI(), 'offset');

    $conn_r = id(new PhabricatorOwnersPackageCommitRelationship())
      ->establishConnection('r');

    switch ($this->view) {
      case 'all':
        $data = queryfx_all(
          $conn_r,
          'SELECT commitPHID FROM %T
            WHERE packagePHID = %s
            ORDER BY id DESC
            LIMIT %d, %d',
          id(new PhabricatorOwnersPackageCommitRelationship())->getTableName(),
          $package->getPHID(),
          $pager->getOffset(),
          $pager->getPageSize() + 1);
        break;

      default:
        throw new Exception("view {$this->view} not recognized");
    }

    $data = $pager->sliceResults($data);
    $data = ipull($data, null, 'commitPHID');

    $list_panel = $this->renderCommitTable($data, $package);
    $list_panel->appendChild($pager);

    return $list_panel;
  }

  private function renderSideNav() {
    $views = array(
      'all' => 'Related to Package',
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

      $rows[] = $row;
    }

    $commit_table = new AphrontTableView($rows);

    $headers = array(
      'Commit',
      'Date',
      'Time',
      'Summary',
    );
    $commit_table->setHeaders($headers);

    $column_classes =
      array(
        '',
        '',
        'right',
        'wide',
      );
    $commit_table->setColumnClasses($column_classes);

    $list_panel = new AphrontPanelView();
    $list_panel->setHeader('Commits Related to package "'.
      phutil_render_tag(
        'a',
        array(
          'href' => '/owners/package/'.$package->getID().'/',
        ),
        phutil_escape_html($package->getName())).
      '"');
    $list_panel->appendChild($commit_table);

    return $list_panel;
  }
}
