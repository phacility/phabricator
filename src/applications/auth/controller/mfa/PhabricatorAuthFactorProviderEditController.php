<?php

final class PhabricatorAuthFactorProviderEditController
  extends PhabricatorAuthFactorProviderController {

  public function handleRequest(AphrontRequest $request) {
    $this->requireApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);

    $engine = id(new PhabricatorAuthFactorProviderEditEngine())
      ->setController($this);

    $id = $request->getURIData('id');
    if (!$id) {
      $factor_key = $request->getStr('providerFactorKey');

      $map = PhabricatorAuthFactor::getAllFactors();
      $factor = idx($map, $factor_key);
      if (!$factor) {
        return $this->buildFactorSelectionResponse();
      }

      $engine
        ->addContextParameter('providerFactorKey', $factor_key)
        ->setProviderFactor($factor);
    }

    return $engine->buildResponse();
  }

  private function buildFactorSelectionResponse() {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $cancel_uri = $this->getApplicationURI('mfa/');

    $factors = PhabricatorAuthFactor::getAllFactors();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setBig(true)
      ->setFlush(true);

    $factors = msortv($factors, 'newSortVector');

    foreach ($factors as $factor_key => $factor) {
      $factor_uri = id(new PhutilURI('/mfa/edit/'))
        ->replaceQueryParam('providerFactorKey', $factor_key);
      $factor_uri = $this->getApplicationURI($factor_uri);

      $is_enabled = $factor->canCreateNewProvider();

      $item = id(new PHUIObjectItemView())
        ->setHeader($factor->getFactorName())
        ->setImageIcon($factor->newIconView())
        ->addAttribute($factor->getFactorCreateHelp());

      if ($is_enabled) {
        $item
          ->setHref($factor_uri)
          ->setClickable(true);
      } else {
        $item->setDisabled(true);
      }

      $create_description = $factor->getProviderCreateDescription();
      if ($create_description) {
        $item->appendChild($create_description);
      }

      $menu->addItem($item);
    }

    return $this->newDialog()
      ->setTitle(pht('Choose Provider Type'))
      ->appendChild($menu)
      ->addCancelButton($cancel_uri);
  }

}
