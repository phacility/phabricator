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

    $engine = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($panel)
      ->setPanelPHID($panel->getPHID())
      ->setParentPanelPHIDs($parent_phids)
      ->setMovable($request->getBool('movable'))
      ->setHeaderMode($request->getStr('headerMode'))
      ->setPanelKey($request->getStr('panelKey'));

    $context_phid = $request->getStr('contextPHID');
    if ($context_phid) {
      $context = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($context_phid))
        ->executeOne();
      if (!$context) {
        return new Aphront404Response();
      }
      $engine->setContextObject($context);
    }

    $rendered_panel = $engine->renderPanel();

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
