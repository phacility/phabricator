<?php
// Copyright 2004-present Facebook. All Rights Reserved.

final class DiffusionLintDetailsController extends DiffusionController {

  public function processRequest() {
    $limit = 500;
    $offset = $this->getRequest()->getInt('offset', 0);

    $drequest = $this->getDiffusionRequest();
    $messages = $this->loadLintMessages($limit, $offset);
    $is_dir = (substr('/'.$drequest->getPath(), -1) == '/');

    $rows = array();
    foreach ($messages as $message) {
      $path = hsprintf(
        '<a href="%s">%s</a>',
        $drequest->generateURI(array(
          'action' => 'lint',
          'path' => $message['path'],
        )),
        substr($message['path'], strlen($drequest->getPath()) + 1));

      $line = hsprintf(
        '<a href="%s">%s</a>',
        $drequest->generateURI(array(
          'action' => 'browse',
          'path' => $message['path'],
          'line' => $message['line'],
        )),
        $message['line']);

      $rows[] = array(
        $path,
        $line,
        phutil_escape_html(ArcanistLintSeverity::getStringForSeverity(
          $message['severity'])),
        phutil_escape_html($message['code']),
        phutil_escape_html($message['name']),
        phutil_escape_html($message['description']),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(array(
        'Path',
        'Line',
        'Severity',
        'Code',
        'Name',
        'Example',
      ))
      ->setColumnClasses(array(
        '',
        'n',
        '',
        'pri',
        '',
        '',
      ))
    ->setColumnVisibility(array(
      $is_dir,
    ));

    $content = array();

    $content[] = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'lint',
      ));

    $pager = id(new AphrontPagerView())
      ->setPageSize($limit)
      ->setOffset($offset)
      ->setHasMorePages(count($messages) >= $limit)
      ->setURI($this->getRequest()->getRequestURI(), 'offset');

    $content[] = id(new AphrontPanelView())
      ->setHeader(pht('%d Lint Message(s)', count($messages)))
      ->appendChild($table)
      ->appendChild($pager);

    $nav = $this->buildSideNav('lint', false);
    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array('title' => array(
        'Lint',
        $drequest->getRepository()->getCallsign(),
      )));
  }

  private function loadLintMessages($limit, $offset) {
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
      'SELECT *
        FROM %T
        WHERE branchID = %d
        AND code = %s
        %Q
        ORDER BY path, code, line
        LIMIT %d OFFSET %d',
      PhabricatorRepository::TABLE_LINTMESSAGE,
      $branch->getID(),
      $drequest->getLint(),
      $where,
      $limit,
      $offset);
  }

}
