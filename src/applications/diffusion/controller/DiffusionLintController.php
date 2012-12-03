<?php
// Copyright 2004-present Facebook. All Rights Reserved.

final class DiffusionLintController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;

    if ($this->getRequest()->getStr('lint') !== null) {
      $controller = new DiffusionLintDetailsController($this->getRequest());
      $controller->setDiffusionRequest($drequest);
      return $this->delegateToController($controller);
    }

    $codes = $this->loadLintCodes();
    $codes = array_reverse(isort($codes, 'n'));

    if (!$drequest) {
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
        phutil_escape_html(ArcanistLintSeverity::getStringForSeverity(
          $code['maxSeverity'])),
        phutil_escape_html($code['code']),
        phutil_escape_html($code['maxName']),
        phutil_escape_html($code['maxDescription']),
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

    $content[] = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'lint',
      ));

    $link = null;
    if ($this->diffusionRequest) {
      $link = hsprintf(
        '<a href="%s">%s</a>',
        $drequest->generateURI(array(
          'action' => 'lint',
          'lint' => '',
        )),
        pht('Switch to List View'));
    }

    $content[] = id(new AphrontPanelView())
      ->setHeader(pht('%d Lint Message(s)', array_sum(ipull($codes, 'n'))))
      ->setCaption($link)
      ->appendChild($table);

    $title = array('Lint');
    if ($this->diffusionRequest) {
      $title[] = $drequest->getCallsign();
      $content = $this->buildSideNav('lint', false)->appendChild($content);
    }

    return $this->buildStandardPageResponse(
      $content,
      array('title' => $title));
  }

  private function loadLintCodes() {
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
        $is_dir = (substr($drequest->getPath(), -1) == '/');
        $where[] = qsprintf(
          $conn,
          'path '.($is_dir ? 'LIKE %>' : '= %s'),
          '/'.$drequest->getPath());
      }
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
        GROUP BY branchID, code',
      PhabricatorRepository::TABLE_LINTMESSAGE,
      implode(' AND ', $where));
  }

}
