<?php

final class DiffusionLintDetailsController extends DiffusionController {

  protected function processDiffusionRequest(AphrontRequest $request) {
    $limit = 500;
    $offset = $request->getInt('offset', 0);

    $drequest = $this->getDiffusionRequest();
    $branch = $drequest->loadBranch();
    $messages = $this->loadLintMessages($branch, $limit, $offset);
    $is_dir = (substr('/'.$drequest->getPath(), -1) == '/');

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

    $pager = id(new PHUIPagerView())
      ->setPageSize($limit)
      ->setOffset($offset)
      ->setHasMorePages(count($messages) >= $limit)
      ->setURI($request->getRequestURI(), 'offset');

    $content[] = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Lint Details'))
      ->setTable($table);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'lint',
      ));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
        $pager,
      ),
      array(
        'title' =>
          array(
            pht('Lint'),
            $drequest->getRepository()->getCallsign(),
          ),
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
