<?php

final class DiffusionLintController extends DiffusionController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $this->getRequest()->getUser();
    $drequest = $this->diffusionRequest;

    if ($request->getStr('lint') !== null) {
      $controller = new DiffusionLintDetailsController($request);
      $controller->setDiffusionRequest($drequest);
      $controller->setCurrentApplication($this->getCurrentApplication());
      return $this->delegateToController($controller);
    }

    $owners = array();
    if (!$drequest) {
      if (!$request->getArr('owner')) {
        $owners[$user->getPHID()] = $user->getFullName();
      } else {
        $phids = $request->getArr('owner');
        $phid = reset($phids);
        $handles = $this->loadViewerHandles(array($phid));
        $owners[$phid] = $handles[$phid]->getFullName();
      }
    }

    $codes = $this->loadLintCodes(array_keys($owners));

    if ($codes && !$drequest) {
      $branches = id(new PhabricatorRepositoryBranch())->loadAllWhere(
        'id IN (%Ld)',
        array_unique(ipull($codes, 'branchID')));

      $repositories = id(new PhabricatorRepository())->loadAllWhere(
        'id IN (%Ld)',
        array_unique(mpull($branches, 'getRepositoryID')));

      $drequests = array();
      foreach ($branches as $id => $branch) {
        $drequests[$id] = DiffusionRequest::newFromDictionary(array(
          'repository' => $repositories[$branch->getRepositoryID()],
          'branch' => $branch->getName(),
        ));
      }
    }

    $rows = array();
    foreach ($codes as $code) {
      if (!$this->diffusionRequest) {
        $drequest = $drequests[$code['branchID']];
      }

      $rows[] = array(
        hsprintf(
          '<a href="%s">%s</a>',
          $drequest->generateURI(array(
            'action' => 'lint',
            'lint' => $code['code'],
          )),
          $code['n']),
        hsprintf(
          '<a href="%s">%s</a>',
          $drequest->generateURI(array(
            'action' => 'browse',
            'lint' => $code['code'],
          )),
          $code['files']),
        hsprintf(
          '<a href="%s">%s</a>',
          $drequest->generateURI(array('action' => 'lint')),
          $drequest->getCallsign()),
        ArcanistLintSeverity::getStringForSeverity($code['maxSeverity']),
        $code['code'],
        $code['maxName'],
        $code['maxDescription'],
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(array(
        'Problems',
        'Files',
        'Repository',
        'Severity',
        'Code',
        'Name',
        'Example',
      ))
      ->setColumnVisibility(array(true, true, !$this->diffusionRequest))
      ->setColumnClasses(array('n', 'n', '', '', 'pri', '', ''));

    $content = array();

    $link = null;
    if ($this->diffusionRequest) {
      $link = hsprintf(
        '<a href="%s">%s</a>',
        $drequest->generateURI(array(
          'action' => 'lint',
          'lint' => '',
        )),
        pht('Switch to List View'));

    } else {
      $form = id(new AphrontFormView())
        ->setUser($user)
        ->setMethod('GET')
        ->appendChild(
          id(new AphrontFormTokenizerControl())
            ->setDatasource('/typeahead/common/users/')
            ->setLimit(1)
            ->setName('owner')
            ->setLabel('Owner')
            ->setValue($owners))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Filter'));
      $content[] = id(new AphrontListFilterView())->appendChild($form);
    }

    $content[] = id(new AphrontPanelView())
      ->setHeader(pht('%d Lint Message(s)', array_sum(ipull($codes, 'n'))))
      ->setCaption($link)
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
      $content = $this->buildSideNav('lint', false)
        ->setCrumbs($crumbs)
        ->appendChild($content);
    } else {
      array_unshift($content, $crumbs);
    }

    return $this->buildApplicationPage(
      $content,
      array('title' => $title));
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
        $repositories = id(new PhabricatorRepository())->loadAllWhere(
          'phid IN (%Ls)',
          array_unique(mpull($paths, 'getRepositoryPHID')));
        $repositories = mpull($repositories, 'getID', 'getPHID');

        $branches = id(new PhabricatorRepositoryBranch())->loadAllWhere(
          'repositoryID IN (%Ld)',
          $repositories);
        $branches = mgroup($branches, 'getRepositoryID');
      }

      foreach ($paths as $path) {
        $branch = idx($branches, $repositories[$path->getRepositoryPHID()]);
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

}
