<?php

final class DiffusionLintController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    if ($this->getRepositoryIdentifierFromRequest($request)) {
      $response = $this->loadDiffusionContext();
      if ($response) {
        return $response;
      }

      $drequest = $this->getDiffusionRequest();
    } else {
      $drequest = null;
    }

    $code = $request->getStr('lint');
    if (strlen($code)) {
      return $this->buildDetailsResponse();
    }

    $owners = array();
    if (!$drequest) {
      if (!$request->getArr('owner')) {
        $owners = array($viewer->getPHID());
      } else {
        $owners = array(head($request->getArr('owner')));
      }
    }

    $codes = $this->loadLintCodes($drequest, $owners);

    if ($codes) {
      $branches = id(new PhabricatorRepositoryBranch())->loadAllWhere(
        'id IN (%Ld)',
        array_unique(ipull($codes, 'branchID')));
      $branches = mpull($branches, null, 'getID');
    } else {
      $branches = array();
    }

    if ($branches) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withIDs(mpull($branches, 'getRepositoryID'))
        ->execute();
      $repositories = mpull($repositories, null, 'getID');
    } else {
      $repositories = array();
    }


    $rows = array();
    $total = 0;
    foreach ($codes as $code) {
      $branch = idx($branches, $code['branchID']);
      if (!$branch) {
        continue;
      }

      $repository = idx($repositories, $branch->getRepositoryID());
      if (!$repository) {
        continue;
      }

      $total += $code['n'];

      if ($drequest) {
        $href_lint = $drequest->generateURI(
          array(
            'action' => 'lint',
            'lint' => $code['code'],
          ));

        $href_browse = $drequest->generateURI(
          array(
            'action' => 'browse',
            'lint' => $code['code'],
          ));

        $href_repo = $drequest->generateURI(
          array(
            'action' => 'lint',
          ));
      } else {
        $href_lint = $repository->generateURI(
          array(
            'action' => 'lint',
            'lint' => $code['code'],
          ));

        $href_browse = $repository->generateURI(
          array(
            'action' => 'browse',
            'lint' => $code['code'],
          ));

        $href_repo = $repository->generateURI(
          array(
            'action' => 'lint',
          ));
      }

      $rows[] = array(
        phutil_tag('a', array('href' => $href_lint), $code['n']),
        phutil_tag('a', array('href' => $href_browse), $code['files']),
        phutil_tag(
          'a',
          array(
            'href' => $href_repo,
          ),
          $repository->getDisplayName()),
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
      ->setColumnVisibility(array(true, true, !$drequest))
      ->setColumnClasses(array('n', 'n', '', '', 'pri', '', ''));

    $content = array();

    if (!$drequest) {
      $form = id(new AphrontFormView())
        ->setUser($viewer)
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
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);

    $title = array('Lint');
    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'lint',
      ));
    $crumbs->setBorder(true);

    if ($drequest) {
      $title[] = $drequest->getRepository()->getDisplayName();
    } else {
      $crumbs->addTextCrumb(pht('All Lint'));
    }

    if ($drequest) {
      $branch = $drequest->loadBranch();

      $header = id(new PHUIHeaderView())
        ->setHeader(pht('Lint: %s', $this->renderPathLinks($drequest, 'lint')))
        ->setUser($viewer)
        ->setHeaderIcon('fa-code');
      $actions = $this->buildActionView($drequest);
      $properties = $this->buildPropertyView(
        $drequest,
        $branch,
        $total,
        $actions);

      $object_box = id(new PHUIObjectBoxView())
        ->setHeader($header)
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->addPropertyList($properties);
    } else {
      $object_box = null;
      $header = id(new PHUIHeaderView())
        ->setHeader(pht('All Lint'))
        ->setHeaderIcon('fa-code');
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $object_box,
        $content,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
        ));
  }

  private function loadLintCodes($drequest, array $owner_phids) {
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


  private function buildDetailsResponse() {
    $request = $this->getRequest();

    $limit = 500;

    $pager = id(new PHUIPagerView())
      ->readFromRequest($request)
      ->setPageSize($limit);

    $offset = $pager->getOffset();

    $drequest = $this->getDiffusionRequest();
    $branch = $drequest->loadBranch();
    $messages = $this->loadLintMessages($branch, $limit, $offset);
    $is_dir = (substr('/'.$drequest->getPath(), -1) == '/');

    $pager->setHasMorePages(count($messages) >= $limit);

    $authors = $this->loadViewerHandles(ipull($messages, 'authorPHID'));

    $rows = array();
    foreach ($messages as $message) {
      $path = phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(array(
            'action' => 'lint',
            'path' => $message['path'],
          )),
        ),
        substr($message['path'], strlen($drequest->getPath()) + 1));

      $line = phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(array(
            'action' => 'browse',
            'path' => $message['path'],
            'line' => $message['line'],
            'commit' => $branch->getLintCommit(),
          )),
        ),
        $message['line']);

      $author = $message['authorPHID'];
      if ($author && $authors[$author]) {
        $author = $authors[$author]->renderLink();
      }

      $rows[] = array(
        $path,
        $line,
        $author,
        ArcanistLintSeverity::getStringForSeverity($message['severity']),
        $message['name'],
        $message['description'],
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(array(
        pht('Path'),
        pht('Line'),
        pht('Author'),
        pht('Severity'),
        pht('Name'),
        pht('Description'),
      ))
      ->setColumnClasses(array('', 'n'))
      ->setColumnVisibility(array($is_dir));

    $content = array();

    $content[] = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Lint Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table)
      ->setPager($pager);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'lint',
      ));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Lint: %s', $drequest->getRepository()->getDisplayName()))
      ->setHeaderIcon('fa-code');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $content,
      ));

    return $this->newPage()
      ->setTitle(
        array(
          pht('Lint'),
          $drequest->getRepository()->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
        ));
  }

  private function loadLintMessages(
    PhabricatorRepositoryBranch $branch,
    $limit,
    $offset) {

    $drequest = $this->getDiffusionRequest();
    if (!$branch) {
      return array();
    }

    $conn = $branch->establishConnection('r');

    $where = array(
      qsprintf($conn, 'branchID = %d', $branch->getID()),
    );

    if ($drequest->getPath() != '') {
      $path = '/'.$drequest->getPath();
      $is_dir = (substr($path, -1) == '/');
      $where[] = ($is_dir
        ? qsprintf($conn, 'path LIKE %>', $path)
        : qsprintf($conn, 'path = %s', $path));
    }

    if ($drequest->getLint() != '') {
      $where[] = qsprintf(
        $conn,
        'code = %s',
        $drequest->getLint());
    }

    return queryfx_all(
      $conn,
      'SELECT *
        FROM %T
        WHERE %Q
        ORDER BY path, code, line LIMIT %d OFFSET %d',
      PhabricatorRepository::TABLE_LINTMESSAGE,
      implode(' AND ', $where),
      $limit,
      $offset);
  }
}
