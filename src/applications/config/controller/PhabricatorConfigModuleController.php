<?php

final class PhabricatorConfigModuleController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $key = $request->getURIData('module');

    $all_modules = PhabricatorConfigModule::getAllModules();
    if (empty($all_modules[$key])) {
      return new Aphront404Response();
    }

    $module = $all_modules[$key];
    $content = $module->renderModuleStatus($request);
    $title = $module->getModuleName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('module/'.$key.'/');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $content = id(new PhabricatorConfigPageView())
      ->setHeader($header)
      ->setContent($content);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content)
      ->addClass('white-background');
  }

}
