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
        $diffusion_link = phutil_tag('em', array(), pht('Not Tracked'));
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

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Repository List'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $panel,
      ),
      array(
        'title' => pht('Repository List'),
      ));
  }

}
