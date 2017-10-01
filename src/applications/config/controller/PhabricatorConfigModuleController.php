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

    $nav = $this->buildSideNavView();
    $nav->selectFilter('module/'.$key.'/');
    $header = $this->buildHeaderView($title);

    $view = $this->buildConfigBoxView($title, $content);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($title)
      ->setBorder(true);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setNavigation($nav)
      ->setFixed(true)
      ->setMainColumn($view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }

}
