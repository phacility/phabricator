<?php

final class PhabricatorDashboardPanelEditController
  extends PhabricatorDashboardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $engine = id(new PhabricatorDashboardPanelEditEngine())
      ->setController($this);

    $id = $request->getURIData('id');
    if (!$id) {
      $dashboard_id = $request->getStr('dashboardID');
      $column_id = $request->getStr('columnID');

      if (strlen($dashboard_id)) {
        $dashboard = id(new PhabricatorDashboardQuery())
          ->setViewer($viewer)
          ->withIDs(array($dashboard_id))
          ->requireCapabilities(
            array(
              PhabricatorPolicyCapability::CAN_VIEW,
              PhabricatorPolicyCapability::CAN_EDIT,
            ))
          ->executeOne();
        if (!$dashboard) {
          return new Aphront404Response();
        }
      } else {
        $dashboard = null;
      }

      if ($dashboard) {
        $cancel_uri = $dashboard->getURI();
      } else {
        $cancel_uri = $this->getApplicationURI('panel/');
      }

      $panel_type = $request->getStr('panelType');
      $panel_types = PhabricatorDashboardPanelType::getAllPanelTypes();
      if (empty($panel_types[$panel_type])) {
        return $this->buildPanelTypeResponse($cancel_uri);
      }

      $engine
        ->addContextParameter('panelType', $panel_type)
        ->addContextParameter('dashboardID', $dashboard_id)
        ->addContextParameter('columnID', $column_id)
        ->setPanelType($panel_type)
        ->setDashboard($dashboard)
        ->setColumnID($column_id);
    }

    return $engine->buildResponse();
  }

  private function buildPanelTypeResponse($cancel_uri) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();

    $base_uri = $request->getRequestURI();
    $base_uri = new PhutilURI($base_uri);

    $menu = id(new PHUIObjectItemListView())
      ->setViewer($viewer)
      ->setFlush(true)
      ->setBig(true);

    $panel_types = PhabricatorDashboardPanelType::getAllPanelTypes();
    foreach ($panel_types as $panel_type) {
      $item = id(new PHUIObjectItemView())
        ->setClickable(true)
        ->setImageIcon($panel_type->getIcon())
        ->setHeader($panel_type->getPanelTypeName())
        ->addAttribute($panel_type->getPanelTypeDescription());

      $type_uri = id(clone $base_uri)
        ->replaceQueryParam('panelType', $panel_type->getPanelTypeKey());

      $item->setHref($type_uri);

      $menu->addItem($item);
    }

    return $this->newDialog()
      ->setTitle(pht('Choose Panel Type'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($menu)
      ->addCancelButton($cancel_uri);
  }

}
