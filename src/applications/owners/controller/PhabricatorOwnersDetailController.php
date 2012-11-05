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
      $repositories = id(new PhabricatorRepository())->loadAllWhere(
        'phid in (%Ls)',
        array_keys($repository_phids));
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

    $rows[] = array(
      'Name',
      phutil_escape_html($package->getName()));
    $rows[] = array(
      'Description',
      phutil_escape_html($package->getDescription()));

    $primary_owner = null;
    $primary_phid = $package->getPrimaryOwnerPHID();
    if ($primary_phid && isset($handles[$primary_phid])) {
      $primary_owner =
        '<strong>'.$handles[$primary_phid]->renderLink().'</strong>';
    }
    $rows[] = array(
      'Primary Owner',
      $primary_owner,
      );

    $owner_links = array();
    foreach ($owners as $owner) {
      $owner_links[] = $handles[$owner->getUserPHID()]->renderLink();
    }
    $owner_links = implode('<br />', $owner_links);
    $rows[] = array(
      'Owners',
      $owner_links);

    $rows[] = array(
      'Auditing',
      $package->getAuditingEnabled() ? 'Enabled' : 'Disabled',
    );

    $path_links = array();
    foreach ($paths as $path) {
      $repo = $repositories[$path->getRepositoryPHID()];
      $href = DiffusionRequest::generateDiffusionURI(
        array(
          'callsign' => $repo->getCallsign(),
          'branch'   => $repo->getDefaultBranch(),
          'path'     => $path->getPath(),
          'action'   => 'browse'
        ));
      $repo_name = '<strong>'.phutil_escape_html($repo->getName()).
                   '</strong>';
      $path_link = phutil_render_tag(
        'a',
        array(
          'href' => (string) $href,
        ),
        phutil_escape_html($path->getPath()));
      $path_links[] = $repo_name.' '.$path_link;
    }
    $path_links = implode('<br />', $path_links);
    $rows[] = array(
      'Paths',
      $path_links);

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'header',
        'wide',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader(
      'Package Details for "'.phutil_escape_html($package->getName()).'"');
    $panel->addButton(
      javelin_render_tag(
        'a',
        array(
          'href' => '/owners/delete/'.$package->getID().'/',
          'class' => 'button grey',
          'sigil' => 'workflow',
        ),
        'Delete Package'));
    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/owners/edit/'.$package->getID().'/',
          'class' => 'button',
        ),
        'Edit Package'));
    $panel->appendChild($table);

    $key = 'package/'.$package->getID();
    $this->setSideNavFilter($key);

    $commit_views = array();

    $commit_uri = id(new PhutilURI('/audit/view/packagecommits/'))
      ->setQueryParams(
        array(
          'phid'    => $package->getPHID(),
        ));

    $attention_query = id(new PhabricatorAuditCommitQuery())
      ->withPackagePHIDs(array($package->getPHID()))
      ->withStatus(PhabricatorAuditCommitQuery::STATUS_OPEN)
      ->needCommitData(true)
      ->needAudits(true)
      ->setLimit(10);
    $attention_commits = $attention_query->execute();
    if ($attention_commits) {
      $view = new PhabricatorAuditCommitListView();
      $view->setUser($user);
      $view->setCommits($attention_commits);

      $commit_views[] = array(
        'view'    => $view,
        'header'  => 'Commits in this Package that Need Attention',
        'button'  => phutil_render_tag(
          'a',
          array(
            'href'  => $commit_uri->alter('status', 'open'),
            'class' => 'button grey',
          ),
          'View All Problem Commits'),
      );
    }

    $all_query = id(new PhabricatorAuditCommitQuery())
      ->withPackagePHIDs(array($package->getPHID()))
      ->needCommitData(true)
      ->needAudits(true)
      ->setLimit(100);
    $all_commits = $all_query->execute();

    $view = new PhabricatorAuditCommitListView();
    $view->setUser($user);
    $view->setCommits($all_commits);
    $view->setNoDataString('No commits in this package.');

    $commit_views[] = array(
      'view'    => $view,
      'header'  => 'Recent Commits in Package',
      'button'  => phutil_render_tag(
        'a',
        array(
          'href'  => $commit_uri,
          'class' => 'button grey',
        ),
        'View All Package Commits'),
    );

    $phids = array();
    foreach ($commit_views as $commit_view) {
      $phids[] = $commit_view['view']->getRequiredHandlePHIDs();
    }
    $phids = array_mergev($phids);
    $handles = $this->loadViewerHandles($phids);

    $commit_panels = array();
    foreach ($commit_views as $commit_view) {
      $commit_panel = new AphrontPanelView();
      $commit_panel->setHeader(phutil_escape_html($commit_view['header']));
      if (isset($commit_view['button'])) {
        $commit_panel->addButton($commit_view['button']);
      }
      $commit_view['view']->setHandles($handles);
      $commit_panel->appendChild($commit_view['view']);

      $commit_panels[] = $commit_panel;
    }

    return $this->buildStandardPageResponse(
      array(
        $panel,
        $commit_panels,
      ),
      array(
        'title' => "Package '".$package->getName()."'",
      ));
  }

  protected function getExtraPackageViews() {
    $package = $this->package;
    return array(
      array('name' => 'Details',
            'key'  => 'package/'.$package->getID(),
        ));
  }

}
