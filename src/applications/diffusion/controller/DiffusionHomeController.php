<?php

final class DiffusionHomeController extends DiffusionController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $shortcuts = id(new PhabricatorRepositoryShortcut())->loadAll();
    if ($shortcuts) {
      $shortcuts = msort($shortcuts, 'getSequence');

      $rows = array();
      foreach ($shortcuts as $shortcut) {
        $rows[] = array(
          phutil_tag(
            'a',
            array(
              'href' => $shortcut->getHref(),
            ),
            $shortcut->getName()),
          $shortcut->getDescription(),
        );
      }

      $shortcut_table = new AphrontTableView($rows);
      $shortcut_table->setHeaders(
        array(
          'Link',
          '',
        ));
      $shortcut_table->setColumnClasses(
        array(
          'pri',
          'wide',
        ));

      $shortcut_panel = new AphrontPanelView();
      $shortcut_panel->setHeader('Shortcuts');
      $shortcut_panel->appendChild($shortcut_table);
    } else {
      $shortcut_panel = null;
    }

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($user)
      ->execute();

    foreach ($repositories as $key => $repo) {
      if (!$repo->isTracked()) {
        unset($repositories[$key]);
      }
    }
    $repositories = msort($repositories, 'getName');

    $repository_ids = mpull($repositories, 'getID');
    $summaries = array();
    $commits = array();
    if ($repository_ids) {
      $summaries = queryfx_all(
        id(new PhabricatorRepository())->establishConnection('r'),
        'SELECT * FROM %T WHERE repositoryID IN (%Ld)',
        PhabricatorRepository::TABLE_SUMMARY,
        $repository_ids);
        $summaries = ipull($summaries, null, 'repositoryID');

      $commit_ids = array_filter(ipull($summaries, 'lastCommitID'));
      if ($commit_ids) {
        $commit = new PhabricatorRepositoryCommit();
        $commits = $commit->loadAllWhere('id IN (%Ld)', $commit_ids);
        $commits = mpull($commits, null, 'getRepositoryID');
      }
    }

    $branch = new PhabricatorRepositoryBranch();
    $lint_messages = queryfx_all(
      $branch->establishConnection('r'),
      'SELECT b.repositoryID, b.name, COUNT(lm.id) AS n
        FROM %T b
        LEFT JOIN %T lm ON b.id = lm.branchID
        GROUP BY b.id',
      $branch->getTableName(),
      PhabricatorRepository::TABLE_LINTMESSAGE);
    $lint_messages = igroup($lint_messages, 'repositoryID');

    $rows = array();
    $show_lint = false;
    foreach ($repositories as $repository) {
      $id = $repository->getID();
      $commit = idx($commits, $id);

      $size = idx(idx($summaries, $id, array()), 'size', '-');
      if ($size != '-') {
        $size = hsprintf(
          '<a href="%s">%s</a>',
          DiffusionRequest::generateDiffusionURI(array(
            'callsign' => $repository->getCallsign(),
            'action' => 'history',
          )),
          number_format($size));
      }

      $lint_count = '';
      $lint_branches = ipull(idx($lint_messages, $id, array()), 'n', 'name');
      $branch = $repository->getDefaultArcanistBranch();
      if (isset($lint_branches[$branch])) {
        $show_lint = true;
        $lint_count = phutil_tag(
          'a',
          array(
            'href' => DiffusionRequest::generateDiffusionURI(array(
              'callsign' => $repository->getCallsign(),
              'action' => 'lint',
            )),
          ),
          number_format($lint_branches[$branch]));
      }

      $date = '-';
      $time = '-';
      if ($commit) {
        $date = phabricator_date($commit->getEpoch(), $user);
        $time = phabricator_time($commit->getEpoch(), $user);
      }

      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => '/diffusion/'.$repository->getCallsign().'/',
          ),
          $repository->getName()),
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()),
        $size,
        $lint_count,
        $commit
          ? DiffusionView::linkCommit(
              $repository,
              $commit->getCommitIdentifier(),
              $commit->getSummary())
          : '-',
        $date,
        $time,
      );
    }

    $repository_tool_uri = PhabricatorEnv::getProductionURI('/repository/');
    $repository_tool     = phutil_tag('a',
      array(
       'href' => $repository_tool_uri,
      ),
      'repository tool');
    $preface = pht('This instance of Phabricator does not have any '.
                   'configured repositories.');
    if ($user->getIsAdmin()) {
      $no_repositories_txt = hsprintf(
        '%s %s',
        $preface,
        pht(
          'To setup one or more repositories, visit the %s.',
          $repository_tool));
    } else {
      $no_repositories_txt = hsprintf(
        '%s %s',
        $preface,
        pht(
          'Ask an administrator to setup one or more repositories '.
          'via the %s.',
          $repository_tool));
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString($no_repositories_txt);
    $table->setHeaders(
      array(
        'Repository',
        'VCS',
        'Commits',
        'Lint',
        'Last',
        'Date',
        'Time',
      ));
    $table->setColumnClasses(
      array(
        'pri',
        '',
        'n',
        'n',
        'wide',
        '',
        'right',
      ));
    $table->setColumnVisibility(
      array(
        true,
        true,
        true,
        $show_lint,
        true,
        true,
        true,
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Browse Repositories');
    $panel->appendChild($table);
    $panel->setNoBackground();

    $crumbs = $this->buildCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('All Repositories'))
        ->setHref($this->getApplicationURI()));

    return $this->buildStandardPageResponse(
      array(
        $crumbs,
        $shortcut_panel,
        $panel,
      ),
      array(
        'title' => 'Diffusion',
      ));
  }

}
