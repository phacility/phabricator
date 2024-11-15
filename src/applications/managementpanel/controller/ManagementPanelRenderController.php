<?php

final class ManagementPanelRenderController extends PhabricatorController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    
    $requestHandler = $this->getRequestHandler($request);
    if ($requestHandler != null) {
        $requestHandler->handleRequest($request);
    }
      
    $classes = id(new PhutilClassMapQuery())
      ->setAncestorClass('ManagementPanel')
      ->setSortMethod('getName')
      ->execute();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI('view/')));

    $groups = mgroup($classes, 'getCategory');
    ksort($groups);
    foreach ($groups as $group => $group_classes) {
      $nav->addLabel($group);
      foreach ($group_classes as $class => $obj) {
        $name = $obj->getName();
        $nav->addFilter($class, $name);
      }
    }

    $id = $request->getURIData('class');
    $selected = $nav->selectFilter($id, head_key($classes));

    $page = $classes[$selected];
    $page->setRequest($this->getRequest());
    $page->setRequestHandler($requestHandler);

    $result = $page->renderPage($request->getUser());
    if ($result instanceof AphrontResponse) {
      // This allows examples to generate dialogs, etc., for demonstration.
      return $result;
    }

    require_celerity_resource('phabricator-ui-example-css');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($page->getName());

    $note = id(new PHUIInfoView())
      ->setTitle(pht('%s', $page->getName()))
      ->appendChild($page->getDescription())
      ->setSeverity(PHUIInfoView::SEVERITY_NODATA);

 

    $nav->appendChild(
      array(
        $crumbs,
        $note,
        $result,
      ));

    return $this->newPage()
      ->setTitle($page->getName())
      ->appendChild($nav);
  }
  
  private function getRequestHandler($request) {
      $panelType = $request->getStr('panelType');
      
      if (strcmp($panelType, ManagementPanelPanelType::MAIN) == 0) {
          return new ManagementPanelMainPanelRequestHandler();
      }
      if (strcmp($panelType, ManagementPanelPanelType::SECOND) == 0) {
        return new ManagementPanelUserDateRequestHandler();
    }
      if (strcmp($panelType, ManagementPanelPanelType::SUMMARY) == 0) {
          return new ManagementPanelSummaryPanelRequestHandler();
      }
      if (strcmp($panelType, ManagementPanelPanelType::DAY_DETAILS) == 0) {
         return new ManagementPanelDayDetailsRequestHandler();
      }
      return null;
  }
}
