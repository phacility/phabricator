<?php

final class PhabricatorDashboardPanelRenderController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $panel = id(new PhabricatorDashboardPanelQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$panel) {
      return new Aphront404Response();
    }

    $rendered_panel = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($panel)
      ->renderPanel();

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())
        ->setContent(
          array(
            'panelMarkup' => $rendered_panel,
          ));
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Panels'), $this->getApplicationURI('panel/'))
      ->addTextCrumb($panel->getMonogram(), '/'.$panel->getMonogram())
      ->addTextCrumb(pht('Standalone View'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $rendered_panel,
      ),
      array(
        'title' => array(pht('Panel'), $panel->getName()),
        'device' => true,
      ));
  }

}
