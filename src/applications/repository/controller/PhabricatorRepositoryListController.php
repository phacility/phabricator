<?php

final class PhabricatorRepositoryListController
  extends PhabricatorRepositoryController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $is_admin = $user->getIsAdmin();

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($user)
      ->execute();
    $repos = msort($repos, 'getName');

    $rows = array();
    foreach ($repos as $repo) {

      if ($repo->isTracked()) {
        $diffusion_link = phutil_tag(
          'a',
          array(
            'href' => '/diffusion/'.$repo->getCallsign().'/',
          ),
          pht('View in Diffusion'));
      } else {
        $diffusion_link = phutil_tag('em', array(), 'Not Tracked');
      }

      $rows[] = array(
        $repo->getCallsign(),
        $repo->getName(),
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repo->getVersionControlSystem()),
        $diffusion_link,
        phutil_tag(
          'a',
          array(
            'class' => 'button small grey',
            'href'  => '/diffusion/'.$repo->getCallsign().'/edit/',
          ),
          pht('Edit')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString(pht('No Repositories'));
    $table->setHeaders(
      array(
        pht('Callsign'),
        pht('Repository'),
        pht('Type'),
        pht('Diffusion'),
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        'wide',
        null,
        null,
        'action',
      ));

    $table->setColumnVisibility(
      array(
        true,
        true,
        true,
        true,
        $is_admin,
      ));

    $panel = new PHUIObjectBoxView();
    $header = new PHUIHeaderView();
    $header->setHeader(pht('Repositories'));
    if ($is_admin) {
      $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('Create New Repository'))
        ->setHref('/diffusion/new/');
      $header->addActionLink($button);
    }
    $panel->setHeader($header);
    $panel->appendChild($table);

    $projects = id(new PhabricatorRepositoryArcanistProject())->loadAll();

    $rows = array();
    foreach ($projects as $project) {
      $repo = idx($repos, $project->getRepositoryID());
      if ($repo) {
        $repo_name = $repo->getName();
      } else {
        $repo_name = '-';
      }

      $rows[] = array(
        $project->getName(),
        $repo_name,
        phutil_tag(
          'a',
          array(
            'href' => '/repository/project/edit/'.$project->getID().'/',
            'class' => 'button grey small',
          ),
          pht('Edit')),
        javelin_tag(
          'a',
          array(
            'href' => '/repository/project/delete/'.$project->getID().'/',
            'class' => 'button grey small',
            'sigil' => 'workflow',
          ),
          pht('Delete')),
      );

    }

    $project_table = new AphrontTableView($rows);
    $project_table->setNoDataString(pht('No Arcanist Projects'));
    $project_table->setHeaders(
      array(
        pht('Project ID'),
        pht('Repository'),
        '',
        '',
      ));
    $project_table->setColumnClasses(
      array(
        '',
        'wide',
        'action',
        'action',
      ));

    $project_table->setColumnVisibility(
      array(
        true,
        true,
        $is_admin,
        $is_admin,
      ));

    $project_panel = new PHUIObjectBoxView();
    $project_panel->setHeaderText(pht('Arcanist Projects'));
    $project_panel->appendChild($project_table);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Repository List'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $panel,
        $project_panel,
      ),
      array(
        'title' => pht('Repository List'),
      ));
  }

}
