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
          phutil_render_tag(
            'a',
            array(
              'href' => $shortcut->getHref(),
            ),
            phutil_escape_html($shortcut->getName())),
          phutil_escape_html($shortcut->getDescription()),
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

    $repository = new PhabricatorRepository();

    $repositories = $repository->loadAll();
    foreach ($repositories as $key => $repo) {
      if (!$repo->isTracked()) {
        unset($repositories[$key]);
      }
    }

    $repository_ids = mpull($repositories, 'getID');
    $summaries = array();
    $commits = array();
    if ($repository_ids) {
      $summaries = queryfx_all(
        $repository->establishConnection('r'),
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

    $rows = array();
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

      $date = '-';
      $time = '-';
      if ($commit) {
        $date = phabricator_date($commit->getEpoch(), $user);
        $time = phabricator_time($commit->getEpoch(), $user);
      }

      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => '/diffusion/'.$repository->getCallsign().'/',
          ),
          phutil_escape_html($repository->getName())),
        phutil_escape_html($repository->getDetail('description')),
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()),
        $size,
        $commit
          ? DiffusionView::linkCommit(
              $repository,
              $commit->getCommitIdentifier())
          : '-',
        $date,
        $time,
      );
    }

    $repository_tool_uri = PhabricatorEnv::getProductionURI('/repository/');
    $repository_tool     = phutil_render_tag('a',
                                             array(
                                               'href' => $repository_tool_uri,
                                             ),
                                             'repository tool');
    $no_repositories_txt = 'This instance of Phabricator does not have any '.
                           'configured repositories. ';
    if ($user->getIsAdmin()) {
      $no_repositories_txt .= 'To setup one or more repositories, visit the '.
                              $repository_tool.'.';
    } else {
      $no_repositories_txt .= 'Ask an administrator to setup one or more '.
                              'repositories via the '.$repository_tool.'.';
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString($no_repositories_txt);
    $table->setHeaders(
      array(
        'Repository',
        'Description',
        'VCS',
        'Commits',
        'Last',
        'Date',
        'Time',
      ));
    $table->setColumnClasses(
      array(
        'pri',
        'wide',
        '',
        'n',
        'n',
        '',
        'right',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Browse Repositories');
    $panel->appendChild($table);

    $crumbs = $this->buildCrumbs();

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
