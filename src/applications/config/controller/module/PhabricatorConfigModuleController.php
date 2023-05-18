<?php

final class PhabricatorConfigModuleController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $key = $request->getURIData('module');

    $all_modules = PhabricatorConfigModule::getAllModules();

    if ($key === null || !strlen($key)) {
      $key = head_key($all_modules);
    }

    if (empty($all_modules[$key])) {
      return new Aphront404Response();
    }

    $module = $all_modules[$key];
    $content = $module->renderModuleStatus($request);
    $title = $module->getModuleName();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $modules_uri = $this->getApplicationURI('module/');

    $modules = PhabricatorConfigModule::getAllModules();

    foreach ($modules as $module_key => $module) {
      $nav->newLink($module_key)
        ->setName($module->getModuleName())
        ->setHref(urisprintf('%s%s/', $modules_uri, $module_key))
        ->setIcon('fa-puzzle-piece');
    }

    $nav->selectFilter($key);
    $header = $this->buildHeaderView($title);

    if ($content instanceof AphrontTableView) {
      $view = $this->buildConfigBoxView($title, $content);
    } else {
      $view = $content;
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Extensions/Modules'), $modules_uri)
      ->addTextCrumb($title)
      ->setBorder(true);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content);
  }

}
