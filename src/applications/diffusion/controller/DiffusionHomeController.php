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
          $shortcut->getName(),
          $shortcut->getHref(),
          $shortcut->getDescription(),
        );
      }

      $list = new PHUIObjectItemListView();
      $list->setCards(true);
      $list->setFlush(true);
      foreach ($rows as $row) {
        $item = id(new PHUIObjectItemView())
            ->setHeader($row[0])
            ->setHref($row[1])
            ->setSubhead(($row[2] ? $row[2] : pht('No Description')));
        $list->addItem($item);
      }

      $shortcut_panel = id(new AphrontPanelView())
        ->setNoBackground(true)
        ->setHeader(pht('Shortcuts'))
        ->appendChild($list);

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
          pht('%s Commits', new PhutilNumber($size)));
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
          pht('%s Lint Messages', new PhutilNumber($lint_branches[$branch])));
      }

      $datetime = '';
      if ($commit) {
        $date = phabricator_date($commit->getEpoch(), $user);
        $time = phabricator_time($commit->getEpoch(), $user);
        $datetime = $date.' '.$time;
      }

      $rows[] = array(
        $repository->getName(),
        ('/diffusion/'.$repository->getCallsign().'/'),
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()),
        $size,
        $lint_count,
        $commit
          ? DiffusionView::linkCommit(
              $repository,
              $commit->getCommitIdentifier(),
              $commit->getSummary())
          : pht('No Commits'),
        $datetime
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

    $list = new PHUIObjectItemListView();
    $list->setCards(true);
    $list->setFlush(true);
    foreach ($rows as $row) {
      $item = id(new PHUIObjectItemView())
          ->setHeader($row[0])
          ->setSubHead($row[5])
          ->setHref($row[1])
          ->addAttribute(($row[2] ? $row[2] : pht('No Information')))
          ->addAttribute(($row[3] ? $row[3] : pht('0 Commits')))
          ->addIcon('none', $row[6]);
      if ($show_lint) {
        $item->addAttribute($row[4]);
      }
      $list->addItem($item);
    }

    $list = id(new AphrontPanelView())
      ->setNoBackground(true)
      ->setHeader(pht('Repositories'))
      ->appendChild($list);


    $crumbs = $this->buildCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('All Repositories'))
        ->setHref($this->getApplicationURI()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $shortcut_panel,
        $list,
      ),
      array(
        'title' => pht('Diffusion'),
        'device' => true,
      ));
  }

}
