<?php

final class PhabricatorDashboardPanelRenderController
  extends PhabricatorDashboardController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $panel = id(new PhabricatorDashboardPanelQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$panel) {
      return new Aphront404Response();
    }

    if ($request->isAjax()) {
      $parent_phids = $request->getStrList('parentPanelPHIDs', null);
      if ($parent_phids === null) {
        throw new Exception(
          pht(
            'Required parameter `parentPanelPHIDs` is not present in '.
            'request.'));
      }
    } else {
      $parent_phids = array();
    }

    $rendered_panel = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($panel)
      ->setParentPanelPHIDs($parent_phids)
      ->setHeaderMode($request->getStr('headerMode'))
      ->setDashboardID($request->getInt('dashboardID'))
      ->renderPanel();

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())
        ->setContent(
          array(
            'panelMarkup' => hsprintf('%s', $rendered_panel),
          ));
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Panels'), $this->getApplicationURI('panel/'))
      ->addTextCrumb($panel->getMonogram(), '/'.$panel->getMonogram())
      ->addTextCrumb(pht('Standalone View'))
      ->setBorder(true);

    $view = id(new PHUIBoxView())
      ->addClass('dashboard-view')
      ->appendChild($rendered_panel);

    return $this->newPage()
      ->setTitle(array(pht('Panel'), $panel->getName()))
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
