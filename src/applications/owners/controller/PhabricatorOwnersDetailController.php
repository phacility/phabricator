<?php

final class PhabricatorOwnersDetailController
  extends PhabricatorOwnersController {

  private $id;
  private $package;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $package = id(new PhabricatorOwnersPackage())->load($this->id);
    if (!$package) {
      return new Aphront404Response();
    }
    $this->package = $package;

    $paths = $package->loadPaths();
    $owners = $package->loadOwners();

    $repository_phids = array();
    foreach ($paths as $path) {
      $repository_phids[$path->getRepositoryPHID()] = true;
    }

    if ($repository_phids) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($user)
        ->withPHIDs(array_keys($repository_phids))
        ->execute();
      $repositories = mpull($repositories, null, 'getPHID');
    } else {
      $repositories = array();
    }

    $phids = array();
    foreach ($owners as $owner) {
      $phids[$owner->getUserPHID()] = true;
    }
    $phids = array_keys($phids);

    $handles = $this->loadViewerHandles($phids);

    $rows = array();

    $rows[] = array(pht('Name'), $package->getName());
    $rows[] = array(pht('Description'), $package->getDescription());

    $primary_owner = null;
    $primary_phid = $package->getPrimaryOwnerPHID();
    if ($primary_phid && isset($handles[$primary_phid])) {
      $primary_owner = phutil_tag(
        'strong',
        array(),
        $handles[$primary_phid]->renderLink());
    }
    $rows[] = array(pht('Primary Owner'), $primary_owner);

    $owner_links = array();
    foreach ($owners as $owner) {
      $owner_links[] = $handles[$owner->getUserPHID()]->renderLink();
    }
    $owner_links = phutil_implode_html(phutil_tag('br'), $owner_links);
    $rows[] = array(pht('Owners'), $owner_links);

    $rows[] = array(
      pht('Auditing'),
      $package->getAuditingEnabled() ?
        pht('Enabled') :
        pht('Disabled'),
    );

    $path_links = array();
    foreach ($paths as $path) {
      $repo = idx($repositories, $path->getRepositoryPHID());
      if (!$repo) {
        continue;
      }
      $href = DiffusionRequest::generateDiffusionURI(
        array(
          'callsign' => $repo->getCallsign(),
          'branch'   => $repo->getDefaultBranch(),
          'path'     => $path->getPath(),
          'action'   => 'browse',
        ));
      $repo_name = phutil_tag('strong', array(), $repo->getName());
      $path_link = phutil_tag(
        'a',
        array(
          'href' => (string) $href,
        ),
        $path->getPath());
      $path_links[] = hsprintf(
        '%s %s %s',
        ($path->getExcluded() ? "\xE2\x80\x93" : '+'),
        $repo_name,
        $path_link);
    }
    $path_links = phutil_implode_html(phutil_tag('br'), $path_links);
    $rows[] = array(pht('Paths'), $path_links);

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'header',
        'wide',
      ));

    $panel = new PHUIObjectBoxView();
    $header = new PHUIHeaderView();
    $header->setHeader(
      pht('Package Details for "%s"', $package->getName()));
    $header->addActionLink(
      id(new PHUIButtonView())
        ->setTag('a')
        ->setHref('/owners/delete/'.$package->getID().'/')
        ->addSigil('workflow')
        ->setText(pht('Delete Package')));

    $header->addActionLink(
      id(new PHUIButtonView())
        ->setTag('a')
        ->setHref('/owners/edit/'.$package->getID().'/')
        ->setText(pht('Edit Package')));

    $panel->setHeader($header);
    $panel->appendChild($table);

    $key = 'package/'.$package->getID();
    $this->setSideNavFilter($key);

    $commit_views = array();

    $commit_uri = id(new PhutilURI('/audit/'))
      ->setQueryParams(
        array(
          'auditorPHIDs' => $package->getPHID(),
        ));

    $attention_commits = id(new DiffusionCommitQuery())
      ->setViewer($request->getUser())
      ->withAuditorPHIDs(array($package->getPHID()))
      ->withAuditStatus(DiffusionCommitQuery::AUDIT_STATUS_CONCERN)
      ->needCommitData(true)
      ->setLimit(10)
      ->execute();
    if ($attention_commits) {
      $view = id(new PhabricatorAuditListView())
        ->setUser($user)
        ->setCommits($attention_commits);

      $commit_views[] = array(
        'view'    => $view,
        'header'  => pht('Commits in this Package that Need Attention'),
        'button'  => id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($commit_uri->alter('status', 'open'))
          ->setText(pht('View All Problem Commits')),
      );
    }

    $all_commits = id(new DiffusionCommitQuery())
      ->setViewer($request->getUser())
      ->withAuditorPHIDs(array($package->getPHID()))
      ->needCommitData(true)
      ->setLimit(100)
      ->execute();

    $view = id(new PhabricatorAuditListView())
      ->setUser($user)
      ->setCommits($all_commits)
      ->setNoDataString(pht('No commits in this package.'));

    $commit_views[] = array(
      'view'    => $view,
      'header'  => pht('Recent Commits in Package'),
      'button'  => id(new PHUIButtonView())
        ->setTag('a')
        ->setHref($commit_uri)
        ->setText(pht('View All Package Commits')),
    );

    $phids = array();
    foreach ($commit_views as $commit_view) {
      $phids[] = $commit_view['view']->getRequiredHandlePHIDs();
    }
    $phids = array_mergev($phids);
    $handles = $this->loadViewerHandles($phids);

    $commit_panels = array();
    foreach ($commit_views as $commit_view) {
      $commit_panel = new PHUIObjectBoxView();
      $header = new PHUIHeaderView();
      $header->setHeader($commit_view['header']);
      if (isset($commit_view['button'])) {
        $header->addActionLink($commit_view['button']);
      }
      $commit_view['view']->setHandles($handles);
      $commit_panel->setHeader($header);
      $commit_panel->appendChild($commit_view['view']);

      $commit_panels[] = $commit_panel;
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($package->getName());

    $nav = $this->buildSideNavView();
    $nav->appendChild($crumbs);
    $nav->appendChild($panel);
    $nav->appendChild($commit_panels);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Package %s', $package->getName()),
      ));
  }

  protected function getExtraPackageViews(AphrontSideNavFilterView $view) {
    $package = $this->package;
    $view->addFilter('package/'.$package->getID(), pht('Details'));
  }

}
