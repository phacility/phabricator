<?php

final class PhabricatorGuideModuleController
  extends PhabricatorGuideController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $key = $request->getURIData('module');

    $all_modules = PhabricatorGuideModule::getEnabledModules();

    if (!$key) {
      $key = key($all_modules);
    }

    $nav = $this->buildSideNavView();
    $nav->selectFilter($key.'/');

    $module = $all_modules[$key];
    $content = $module->renderModuleStatus($request);
    $title = $module->getModuleName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $view = id(new PHUICMSView())
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->setHeader($header)
      ->setContent($content);

    return $this->newPage()
      ->setTitle($title)
      ->addClass('phui-cms-body')
      ->appendChild($view);
  }

}
