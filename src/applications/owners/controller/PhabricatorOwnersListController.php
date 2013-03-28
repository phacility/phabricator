<?php

final class PhabricatorOwnersListController
  extends PhabricatorOwnersController {

  protected $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view', 'owned');
    $this->setSideNavFilter('view/'.$this->view);
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $package = new PhabricatorOwnersPackage();
    $owner = new PhabricatorOwnersOwner();
    $path = new PhabricatorOwnersPath();

    $repository_phid = '';
    if ($request->getStr('repository') != '') {
      $repository_phid = id(new PhabricatorRepository())
        ->loadOneWhere('callsign = %s', $request->getStr('repository'))
        ->getPHID();
    }

    switch ($this->view) {
      case 'search':
        $packages = array();

        $conn_r = $package->establishConnection('r');

        $where = array('1 = 1');
        $join = array();
        $having = '';

        if ($request->getStr('name')) {
          $where[] = qsprintf(
            $conn_r,
            'p.name LIKE %~',
            $request->getStr('name'));
        }

        if ($repository_phid || $request->getStr('path')) {

          $join[] = qsprintf(
            $conn_r,
            'JOIN %T path ON path.packageID = p.id',
            $path->getTableName());

          if ($repository_phid) {
            $where[] = qsprintf(
              $conn_r,
              'path.repositoryPHID = %s',
              $repository_phid);
          }

          if ($request->getStr('path')) {
            $where[] = qsprintf(
              $conn_r,
              '(path.path LIKE %~ AND NOT path.excluded) OR
                %s LIKE CONCAT(REPLACE(path.path, %s, %s), %s)',
              $request->getStr('path'),
              $request->getStr('path'),
              '_',
              '\_',
              '%');
            $having = 'HAVING MAX(path.excluded) = 0';
          }

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
          'SELECT p.* FROM %T p %Q WHERE %Q GROUP BY p.id %Q',
          $package->getTableName(),
          implode(' ', $join),
          '('.implode(') AND (', $where).')',
          $having);
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
      case 'projects':
        $projects = id(new PhabricatorProjectQuery())
          ->setViewer($user)
          ->withMemberPHIDs(array($user->getPHID()))
          ->withStatus(PhabricatorProjectQuery::STATUS_ANY)
          ->execute();
        $owner_phids = mpull($projects, 'getPHID');
        $data = queryfx_all(
          $package->establishConnection('r'),
          'SELECT p.* FROM %T p JOIN %T o ON p.id = o.packageID
            WHERE o.userPHID IN (%Ls) GROUP BY p.id',
          $package->getTableName(),
          $owner->getTableName(),
          $owner_phids);
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

    $owners_search_value = array();
    if ($request->getArr('owner')) {
      $phids = $request->getArr('owner');
      $phid = reset($phids);
      $handles = $this->loadViewerHandles(array($phid));
      $owners_search_value = array(
        $phid => $handles[$phid]->getFullName(),
      );
    }

    $callsigns = array('' => '(Any Repository)');
    $repositories = id(new PhabricatorRepository())
      ->loadAllWhere('1 = 1 ORDER BY callsign');
    foreach ($repositories as $repository) {
      $callsigns[$repository->getCallsign()] =
        $repository->getCallsign().': '.$repository->getName();
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/owners/view/search/')
      ->setMethod('GET')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel('Name')
          ->setValue($request->getStr('name')))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/usersorprojects/')
          ->setLimit(1)
          ->setName('owner')
          ->setLabel('Owner')
          ->setValue($owners_search_value))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('repository')
          ->setLabel('Repository')
          ->setOptions($callsigns)
          ->setValue($request->getStr('repository')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('path')
          ->setLabel('Path')
          ->setValue($request->getStr('path')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Search for Packages'));

    $filter->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $filter,
        $content,
      ),
      array(
        'title' => 'Package Index',
      ));
  }

  private function renderPackageTable(array $packages, $header, $nodata) {
    assert_instances_of($packages, 'PhabricatorOwnersPackage');

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
      $phids = array_keys($phids);
      $handles = $this->loadViewerHandles($phids);

      $repository_phids = array();
      foreach ($paths as $path) {
        $repository_phids[$path->getRepositoryPHID()] = true;
      }

      if ($repository_phids) {
        $repositories = id(new PhabricatorRepository())->loadAllWhere(
          'phid in (%Ls)',
          array_keys($repository_phids));
      } else {
        $repositories = array();
      }

      $repositories = mpull($repositories, null, 'getPHID');
      $owners       = mgroup($owners, 'getPackageID');
      $paths        = mgroup($paths, 'getPackageID');
    } else {
      $handles      = array();
      $repositories = array();
      $owners       = array();
      $paths        = array();
    }

    $rows = array();
    foreach ($packages as $package) {

      $pkg_owners = idx($owners, $package->getID(), array());
      foreach ($pkg_owners as $key => $owner) {
        $pkg_owners[$key] = $handles[$owner->getUserPHID()]->renderLink();
        if ($owner->getUserPHID() == $package->getPrimaryOwnerPHID()) {
          $pkg_owners[$key] = phutil_tag('strong', array(), $pkg_owners[$key]);
        }
      }
      $pkg_owners = phutil_implode_html(phutil_tag('br'), $pkg_owners);

      $pkg_paths = idx($paths, $package->getID(), array());
      foreach ($pkg_paths as $key => $path) {
        $repo = idx($repositories, $path->getRepositoryPHID());
        if ($repo) {
          $href = DiffusionRequest::generateDiffusionURI(
            array(
              'callsign' => $repo->getCallsign(),
              'branch'   => $repo->getDefaultBranch(),
              'path'     => $path->getPath(),
              'action'   => 'browse',
            ));
          $pkg_paths[$key] = hsprintf(
            '%s %s%s',
            ($path->getExcluded() ? "\xE2\x80\x93" : '+'),
            phutil_tag('strong', array(), $repo->getName()),
            phutil_tag(
              'a',
              array(
                'href' => (string) $href,
              ),
              $path->getPath()));
        } else {
          $pkg_paths[$key] = $path->getPath();
        }
      }
      $pkg_paths = phutil_implode_html(phutil_tag('br'), $pkg_paths);

      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => '/owners/package/'.$package->getID().'/',
          ),
          $package->getName()),
        $pkg_owners,
        $pkg_paths,
        phutil_tag(
          'a',
          array(
            'href' => '/audit/view/packagecommits/?phid='.$package->getPHID(),
          ),
          'Related Commits')
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
    $panel->setNoBackground();

    return $panel;
  }

  protected function getExtraPackageViews(AphrontSideNavFilterView $view) {
    if ($this->view == 'search') {
      $view->addFilter('view/search', 'Search Results');
    }
  }
}
