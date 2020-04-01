<?php

final class DiffusionRepositoryEditController
  extends DiffusionRepositoryManageController {

  public function handleRequest(AphrontRequest $request) {
    $engine = id(new DiffusionRepositoryEditEngine())
      ->setController($this);

    $id = $request->getURIData('id');
    if (!$id) {
      $this->requireApplicationCapability(
        DiffusionCreateRepositoriesCapability::CAPABILITY);

      $vcs = $request->getStr('vcs');
      $vcs_types = PhabricatorRepositoryType::getRepositoryTypeMap();
      if (empty($vcs_types[$vcs])) {
        return $this->buildVCSTypeResponse();
      }

      $engine
        ->addContextParameter('vcs', $vcs)
        ->setVersionControlSystem($vcs);
    }

    return $engine->buildResponse();
  }

  private function buildVCSTypeResponse() {
    $vcs_types = PhabricatorRepositoryType::getRepositoryTypeMap();

    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Create Repository'));
    $crumbs->setBorder(true);

    $title = pht('Choose Repository Type');

    $layout = id(new AphrontMultiColumnView())
      ->setFluidLayout(true);

    $create_uri = $request->getRequestURI();

    foreach ($vcs_types as $vcs_key => $vcs_type) {
      $image = idx($vcs_type, 'image');
      $image = PhabricatorFile::loadBuiltin($viewer, $image);
      $action = id(new PHUIActionPanelView())
        ->setImage($image->getBestURI())
        ->setHeader(idx($vcs_type, 'create.header'))
        ->setHref($create_uri->alter('vcs', $vcs_key))
        ->setSubheader(idx($vcs_type, 'create.subheader'));

      $layout->addColumn($action);
    }

    $hints = id(new AphrontMultiColumnView())
      ->setFluidLayout(true);

    $observe_href = PhabricatorEnv::getDoclink(
      'Diffusion User Guide: Existing Repositories');

    require_celerity_resource('diffusion-css');

    $image = PhabricatorFile::loadBuiltin($viewer, 'repo/repo.png');
    $hints->addColumn(
      id(new PHUIActionPanelView())
        ->setImage($image->getBestURI())
        ->setHeader(pht('Import or Observe an Existing Repository'))
        ->setHref($observe_href)
        ->setSubheader(
          pht(
            'Review the documentation describing how to import or observe an '.
            'existing repository.')));

    $layout = id(new PHUIBoxView())
      ->addClass('diffusion-create-repo')
      ->appendChild($layout);

    $launcher_view = id(new PHUILauncherView())
      ->appendChild(
        array(
          $layout,
          $hints,
        ));

    $view = id(new PHUITwoColumnView())
      ->setFooter($launcher_view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
