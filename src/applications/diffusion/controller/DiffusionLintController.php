<?php
// Copyright 2004-present Facebook. All Rights Reserved.

final class DiffusionLintController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();

    $codes = $this->loadLintCodes();
    $codes = array_reverse(isort($codes, 'n'));

    $rows = array();
    foreach ($codes as $code) {
      $rows[] = array(
        $code['n'],
        hsprintf(
          '<a href="%s">%s</a>',
          $drequest->generateURI(array(
            'action' => 'browse',
            'lint' => $code['code'],
          )),
          $code['files']),
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
        'Severity',
        'Code',
        'Name',
        'Example',
      ))
      ->setColumnClasses(array(
        'n',
        'n',
        '',
        'pri',
        '',
        '',
      ));

    $content = array();

    $content[] = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'lint',
      ));

    $content[] = id(new AphrontPanelView())
      ->setHeader(array_sum(ipull($codes, 'n')).' Lint Messages')
      ->appendChild($table);

    $nav = $this->buildSideNav('lint', false);
    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array('title' => array(
        'Lint',
        $drequest->getRepository()->getCallsign(),
      )));
  }

  private function loadLintCodes() {
    $drequest = $this->getDiffusionRequest();
    $branch = $drequest->loadBranch();
    if (!$branch) {
      return array();
    }

    $conn = $branch->establishConnection('r');

    $where = '';
    if ($drequest->getPath() != '') {
      $is_dir = (substr($drequest->getPath(), -1) == '/');
      $where = qsprintf(
        $conn,
        'AND path '.($is_dir ? 'LIKE %>' : '= %s'),
        '/'.$drequest->getPath());
    }

    return queryfx_all(
      $conn,
      'SELECT
          code,
          MAX(severity) AS maxSeverity,
          MAX(name) AS maxName,
          MAX(description) AS maxDescription,
          COUNT(DISTINCT path) AS files,
          COUNT(*) AS n
        FROM %T
        WHERE branchID = %d
        %Q
        GROUP BY code',
      PhabricatorRepository::TABLE_LINTMESSAGE,
      $branch->getID(),
      $where);
  }

}
