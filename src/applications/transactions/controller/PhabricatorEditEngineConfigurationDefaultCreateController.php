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
      $title = pht('Unmark as Create Form');
      $body = pht(
        'Unmark this form as a create form? It will still function properly, '.
        'but no longer be reachable directly from the application "Create" '.
        'menu.');
      $button = pht('Unmark Form');
    } else {
      $title = pht('Mark as Create Form');
      $body = pht(
        'Mark this form as a create form? It will appear in the application '.
        '"Create" menus by default.');
      $button = pht('Mark Form');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitButton($button)
      ->addCancelbutton($cancel_uri);
  }

}
