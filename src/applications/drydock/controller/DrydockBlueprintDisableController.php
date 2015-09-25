<?php

final class DrydockBlueprintDisableController
  extends DrydockBlueprintController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $blueprint = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$blueprint) {
      return new Aphront404Response();
    }

    $is_disable = ($request->getURIData('action') == 'disable');
    $id = $blueprint->getID();
    $cancel_uri = $this->getApplicationURI("blueprint/{$id}/");

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new DrydockBlueprintTransaction())
        ->setTransactionType(DrydockBlueprintTransaction::TYPE_DISABLED)
        ->setNewValue($is_disable ? 1 : 0);

      $editor = id(new DrydockBlueprintEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($blueprint, $xactions);

      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    if ($is_disable) {
      $title = pht('Disable Blueprint');
      $body = pht(
        'If you disable this blueprint, Drydock will no longer use it to '.
        'allocate new resources. Existing resources will not be affected.');
      $button = pht('Disable Blueprint');
    } else {
      $title = pht('Enable Blueprint');
      $body = pht(
        'If you enable this blueprint, Drydock will start using it to '.
        'allocate new resources.');
      $button = pht('Enable Blueprint');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton($button);
  }
}
