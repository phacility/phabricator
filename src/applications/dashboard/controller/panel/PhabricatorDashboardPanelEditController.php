<?php

final class PhabricatorDashboardPanelEditController
  extends PhabricatorDashboardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $engine = id(new PhabricatorDashboardPanelEditEngine())
      ->setController($this);

    // We can create or edit a panel in the context of a dashboard or
    // container panel, like a tab panel. If we started this flow on some
    // container object, we want to return to that container when we're done
    // editing.

    $context_phid = $request->getStr('contextPHID');
    if (phutil_nonempty_string($context_phid)) {
      $context = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($context_phid))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$context) {
        return new Aphront404Response();
      }

      if (!($context instanceof PhabricatorDashboardPanelContainerInterface)) {
        return new Aphront404Response();
      }

      $engine
        ->setContextObject($context)
        ->addContextParameter('contextPHID', $context_phid);
    } else {
      $context = null;
    }

    $id = $request->getURIData('id');
    if (!$id) {
      $column_key = $request->getStr('columnKey');

      if ($context) {
        $cancel_uri = $context->getURI();
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
        ->addContextParameter('columnKey', $column_key)
        ->setPanelType($panel_type)
        ->setColumnKey($column_key);
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
