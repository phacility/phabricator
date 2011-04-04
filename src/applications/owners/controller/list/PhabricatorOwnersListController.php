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

class PhabricatorOwnersListController extends PhabricatorOwnersController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $views = array(
      'owned'   => 'Owned Packages',
      'all'     => 'All Packages',
      'search'  => 'Search Results',
    );

    if (empty($views[$this->view])) {
      reset($views);
      $this->view = key($views);
    }

    if ($this->view != 'search') {
      unset($views['search']);
    }

    $nav = new AphrontSideNavView();
    foreach ($views as $key => $name) {
      $nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/owners/view/'.$key.'/',
            'class' => ($this->view == $key)
              ? 'aphront-side-nav-selected'
              : null,
          ),
          phutil_escape_html($name)));
    }

    switch ($this->view) {
      case 'search':
        $content = $this->renderPackageTable(array(), 'Search Results');
        break;
      case 'owned':
        $content = $this->renderOwnedView();
        break;
      case 'all':
        $content = $this->renderAllView();
        break;
    }

    $filter = new AphrontListFilterView();
    $filter->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/owners/new/',
          'class' => 'green button',
        ),
        'Create New Package'));

    $owners_search_value = array();
    if ($request->getArr('owner')) {
      $phids = $request->getArr('owner');
      $phid = reset($phids);
      $handles = id(new PhabricatorObjectHandleData(array($phid)))
        ->loadHandles();
      $owners_search_value = array(
        $phid => $handles[$phid]->getFullName(),
      );
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/owners/view/search/')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel('Name')
          ->setValue($request->getStr('name')))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setLimit(1)
          ->setName('owner')
          ->setLabel('Owner')
          ->setValue($owners_search_value))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('path')
          ->setLabel('Path')
          ->setValue($request->getStr('path')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Search for Packages'));

    $filter->appendChild($form);

    $nav->appendChild($filter);
    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'List',
      ));
  }

  private function renderOwnedView() {
    $packages = array();

    return $this->renderPackageTable($packages, 'Owned Packages');
  }

  private function renderAllView() {
    $packages = array();

    return $this->renderPackageTable($packages, 'All Packages');
  }

  private function renderPackageTable(array $packages, $header) {

    $rows = array();
    foreach ($packages as $package) {
      $rows[] = array(
        'x',
        'y',
        'z',
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Name',
        'Owners',
        'Paths',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide wrap',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->appendChild($table);

    return $panel;
  }

}
