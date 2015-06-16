<?php

final class DiffusionLintController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $drequest = $this->diffusionRequest;

    if ($request->getStr('lint') !== null) {
      $controller = new DiffusionLintDetailsController();
      $controller->setDiffusionRequest($drequest);
      $controller->setCurrentApplication($this->getCurrentApplication());
      return $this->delegateToController($controller);
    }

    $owners = array();
    if (!$drequest) {
      if (!$request->getArr('owner')) {
        $owners = array($user->getPHID());
      } else {
        $owners = array(head($request->getArr('owner')));
      }
    }

    $codes = $this->loadLintCodes($owners);

    if ($codes && !$drequest) {
      // TODO: Build some real Query classes for this stuff.

      $branches = id(new PhabricatorRepositoryBranch())->loadAllWhere(
        'id IN (%Ld)',
        array_unique(ipull($codes, 'branchID')));

      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($user)
        ->withIDs(mpull($branches, 'getRepositoryID'))
        ->execute();

      $drequests = array();
      foreach ($branches as $id => $branch) {
        if (empty($repositories[$branch->getRepositoryID()])) {
          continue;
        }

        $drequests[$id] = DiffusionRequest::newFromDictionary(array(
          'user' => $user,
          'repository' => $repositories[$branch->getRepositoryID()],
          'branch' => $branch->getName(),
        ));
      }
    }

    $rows = array();
    $total = 0;
    foreach ($codes as $code) {
      if (!$this->diffusionRequest) {
        $drequest = idx($drequests, $code['branchID']);
      }

      if (!$drequest) {
        continue;
      }

      $total += $code['n'];

      $href_lint = $drequest->generateURI(array(
        'action' => 'lint',
        'lint' => $code['code'],
      ));
      $href_browse = $drequest->generateURI(array(
        'action' => 'browse',
        'lint' => $code['code'],
      ));
      $href_repo = $drequest->generateURI(array('action' => 'lint'));

      $rows[] = array(
        phutil_tag('a', array('href' => $href_lint), $code['n']),
        phutil_tag('a', array('href' => $href_browse), $code['files']),
        phutil_tag('a', array('href' => $href_repo), $drequest->getCallsign()),
        ArcanistLintSeverity::getStringForSeverity($code['maxSeverity']),
        $code['code'],
        $code['maxName'],
        $code['maxDescription'],
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(array(
        pht('Problems'),
        pht('Files'),
        pht('Repository'),
        pht('Severity'),
        pht('Code'),
        pht('Name'),
        pht('Example'),
      ))
      ->setColumnVisibility(array(true, true, !$this->diffusionRequest))
      ->setColumnClasses(array('n', 'n', '', '', 'pri', '', ''));

    $content = array();

    if (!$this->diffusionRequest) {
      $form = id(new AphrontFormView())
        ->setUser($user)
        ->setMethod('GET')
        ->appendControl(
          id(new AphrontFormTokenizerControl())
            ->setDatasource(new PhabricatorPeopleDatasource())
            ->setLimit(1)
            ->setName('owner')
            ->setLabel(pht('Owner'))
            ->setValue($owners))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Filter')));
      $content[] = id(new AphrontListFilterView())->appendChild($form);
    }

    $content[] = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Lint'))
      ->appendChild($table);

    $title = array('Lint');
    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'lint',
      ));

    if ($this->diffusionRequest) {
      $title[] = $drequest->getCallsign();
    } else {
      $crumbs->addTextCrumb(pht('All Lint'));
    }

    if ($this->diffusionRequest) {
      $branch = $drequest->loadBranch();

      $header = id(new PHUIHeaderView())
        ->setHeader($this->renderPathLinks($drequest, 'lint'))
        ->setUser($user)
        ->setPolicyObject($drequest->getRepository());
      $actions = $this->buildActionView($drequest);
      $properties = $this->buildPropertyView(
        $drequest,
        $branch,
        $total,
        $actions);

      $object_box = id(new PHUIObjectBoxView())
        ->setHeader($header)
        ->addPropertyList($properties);
    } else {
      $object_box = null;
    }


    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $content,
      ),
      array(
        'title' => $title,
      ));
  }

  private function loadLintCodes(array $owner_phids) {
    $drequest = $this->diffusionRequest;
    $conn = id(new PhabricatorRepository())->establishConnection('r');
    $where = array('1 = 1');

    if ($drequest) {
      $branch = $drequest->loadBranch();
      if (!$branch) {
        return array();
      }

      $where[] = qsprintf($conn, 'branchID = %d', $branch->getID());

      if ($drequest->getPath() != '') {
        $path = '/'.$drequest->getPath();
        $is_dir = (substr($path, -1) == '/');
        $where[] = ($is_dir
          ? qsprintf($conn, 'path LIKE %>', $path)
          : qsprintf($conn, 'path = %s', $path));
      }
    }

    if ($owner_phids) {
      $or = array();
      $or[] = qsprintf($conn, 'authorPHID IN (%Ls)', $owner_phids);

      $paths = array();
      $packages = id(new PhabricatorOwnersOwner())
        ->loadAllWhere('userPHID IN (%Ls)', $owner_phids);
      if ($packages) {
        $paths = id(new PhabricatorOwnersPath())->loadAllWhere(
          'packageID IN (%Ld)',
          mpull($packages, 'getPackageID'));
      }

      if ($paths) {
        $repositories = id(new PhabricatorRepositoryQuery())
          ->setViewer($this->getRequest()->getUser())
          ->withPHIDs(mpull($paths, 'getRepositoryPHID'))
          ->execute();
        $repositories = mpull($repositories, 'getID', 'getPHID');

        $branches = id(new PhabricatorRepositoryBranch())->loadAllWhere(
          'repositoryID IN (%Ld)',
          $repositories);
        $branches = mgroup($branches, 'getRepositoryID');
      }

      foreach ($paths as $path) {
        $branch = idx(
          $branches,
          idx(
            $repositories,
            $path->getRepositoryPHID()));
        if ($branch) {
          $condition = qsprintf(
            $conn,
            '(branchID IN (%Ld) AND path LIKE %>)',
            array_keys($branch),
            $path->getPath());
          if ($path->getExcluded()) {
            $where[] = 'NOT '.$condition;
          } else {
            $or[] = $condition;
          }
        }
      }
      $where[] = '('.implode(' OR ', $or).')';
    }

    return queryfx_all(
      $conn,
      'SELECT
          branchID,
          code,
          MAX(severity) AS maxSeverity,
          MAX(name) AS maxName,
          MAX(description) AS maxDescription,
          COUNT(DISTINCT path) AS files,
          COUNT(*) AS n
        FROM %T
        WHERE %Q
        GROUP BY branchID, code
        ORDER BY n DESC',
      PhabricatorRepository::TABLE_LINTMESSAGE,
      implode(' AND ', $where));
  }

  protected function buildActionView(DiffusionRequest $drequest) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $list_uri = $drequest->generateURI(
      array(
        'action' => 'lint',
        'lint' => '',
      ));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View As List'))
        ->setHref($list_uri)
        ->setIcon('fa-list'));

    $history_uri = $drequest->generateURI(
      array(
        'action' => 'history',
      ));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View History'))
        ->setHref($history_uri)
        ->setIcon('fa-clock-o'));

    $browse_uri = $drequest->generateURI(
      array(
        'action' => 'browse',
      ));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Browse Content'))
        ->setHref($browse_uri)
        ->setIcon('fa-files-o'));

    return $view;
  }

  protected function buildPropertyView(
    DiffusionRequest $drequest,
    PhabricatorRepositoryBranch $branch,
    $total,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $callsign = $drequest->getRepository()->getCallsign();
    $lint_commit = $branch->getLintCommit();

    $view->addProperty(
      pht('Lint Commit'),
      phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'commit',
              'commit' => $lint_commit,
            )),
        ),
        $drequest->getRepository()->formatCommitName($lint_commit)));

    $view->addProperty(
      pht('Total Messages'),
      pht('%s', new PhutilNumber($total)));

    return $view;
  }


}
