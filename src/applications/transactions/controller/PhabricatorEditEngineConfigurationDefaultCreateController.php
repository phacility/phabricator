<?php

final class PhabricatorEditEngineConfigurationDefaultCreateController
  extends PhabricatorEditEngineController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $config = $this->loadConfigForEdit();
    if (!$config) {
      return id(new Aphront404Response());
    }

    $engine_key = $config->getEngineKey();
    $key = $config->getIdentifier();
    $cancel_uri = "/transactions/editengine/{$engine_key}/view/{$key}/";

    $type = PhabricatorEditEngineConfigurationTransaction::TYPE_DEFAULTCREATE;

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorEditEngineConfigurationTransaction())
        ->setTransactionType($type)
        ->setNewValue(!$config->getIsDefault());

      $editor = id(new PhabricatorEditEngineConfigurationEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true);

      $editor->applyTransactions($config, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($cancel_uri);
    }

    if ($config->getIsDefault()) {
      $title = pht('Remove From "Create" Menu');
      $body = pht(
        'Remove this form from the application "Create" menu? It will still '.
        'function properly, but no longer be reachable directly from the '.
        'application.');
      $button = pht('Remove From Menu');
    } else {
      $title = pht('Add To "Create" Menu');
      $body = pht(
        'Add this form to the application "Create" menu? Users will '.
        'be able to choose it when creating new objects.');
      $button = pht('Add To Menu');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitButton($button)
      ->addCancelbutton($cancel_uri);
  }

}
