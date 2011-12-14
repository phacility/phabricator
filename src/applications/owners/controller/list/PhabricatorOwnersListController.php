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

    $package = new PhabricatorOwnersPackage();
    $owner = new PhabricatorOwnersOwner();
    $path = new PhabricatorOwnersPath();

    switch ($this->view) {
      case 'search':
        $packages = array();

        $conn_r = $package->establishConnection('r');

        $where = array('1 = 1');
        $join = array();

        if ($request->getStr('name')) {
          $where[] = qsprintf(
            $conn_r,
            'p.name LIKE %~',
            $request->getStr('name'));
        }

        if ($request->getStr('path')) {
          $join[] = qsprintf(
            $conn_r,
            'JOIN %T path ON path.packageID = p.id',
            $path->getTableName());
          $where[] = qsprintf(
            $conn_r,
            'path.path LIKE %~',
            $request->getStr('path'));
        }

        if ($request->getArr('owner')) {
          $join[] = qsprintf(
            $conn_r,
            'JOIN %T o ON o.packageID = p.id',
            $owner->getTableName());
          $where[] = qsprintf(
            $conn_r,
            'o.userPHID IN (%Ls)',
            $request->getArr('owner'));
        }

        $data = queryfx_all(
          $conn_r,
          'SELECT p.* FROM %T p %Q WHERE %Q GROUP BY p.id',
          $package->getTableName(),
          implode(' ', $join),
          '('.implode(') AND (', $where).')');
        $packages = $package->loadAllFromArray($data);

        $header = 'Search Results';
        $nodata = 'No packages match your query.';
        break;
      case 'owned':
        $data = queryfx_all(
          $package->establishConnection('r'),
          'SELECT p.* FROM %T p JOIN %T o ON p.id = o.packageID
            WHERE o.userPHID = %s GROUP BY p.id',
          $package->getTableName(),
          $owner->getTableName(),
          $user->getPHID());
        $packages = $package->loadAllFromArray($data);

        $header = 'Owned Packages';
        $nodata = 'No owned packages';
        break;
      case 'all':
        $packages = $package->loadAll();

        $header = 'All Packages';
        $nodata = 'There are no defined packages.';
        break;
    }

    $content = $this->renderPackageTable(
      $packages,
      $header,
      $nodata);

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
        'title' => 'Package Index',
        'tab' => 'index',
      ));
  }

  private function renderPackageTable(array $packages, $header, $nodata) {

    if ($packages) {
      $package_ids = mpull($packages, 'getID');

      $owners = id(new PhabricatorOwnersOwner())->loadAllWhere(
        'packageID IN (%Ld)',
        $package_ids);

      $paths = id(new PhabricatorOwnersPath())->loadAllWhere(
        'packageID in (%Ld)',
        $package_ids);

      $phids = array();
      foreach ($owners as $owner) {
        $phids[$owner->getUserPHID()] = true;
      }
      foreach ($paths as $path) {
        $phids[$path->getRepositoryPHID()] = true;
      }
      $phids = array_keys($phids);

      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

      $owners = mgroup($owners, 'getPackageID');
      $paths = mgroup($paths, 'getPackageID');
    } else {
      $handles = array();
      $owners = array();
      $paths = array();
    }

    $rows = array();
    foreach ($packages as $package) {

      $pkg_owners = idx($owners, $package->getID(), array());
      foreach ($pkg_owners as $key => $owner) {
        $pkg_owners[$key] = $handles[$owner->getUserPHID()]->renderLink();
        if ($owner->getUserPHID() == $package->getPrimaryOwnerPHID()) {
          $pkg_owners[$key] = '<strong>'.$pkg_owners[$key].'</strong>';
        }
      }
      $pkg_owners = implode('<br />', $pkg_owners);

      $pkg_paths = idx($paths, $package->getID(), array());
      foreach ($pkg_paths as $key => $path) {
        $repo = $handles[$path->getRepositoryPHID()]->getName();
        $pkg_paths[$key] =
          '<strong>'.$repo.'</strong> '.
          phutil_escape_html($path->getPath());
      }
      $pkg_paths = implode('<br />', $pkg_paths);

      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => '/owners/package/'.$package->getID().'/',
          ),
          phutil_escape_html($package->getName())),
        $pkg_owners,
        $pkg_paths,
        phutil_render_tag(
          'a',
          array(
            'href' => '/owners/related/view/all/?phid='.$package->getPHID(),
          ),
          phutil_escape_html('Related Commits'))
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Name',
        'Owners',
        'Paths',
        'Related Commits',
      ));
    $table->setColumnClasses(
      array(
        'pri',
        '',
        'wide wrap',
        'narrow',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->appendChild($table);

    return $panel;
  }

}
