<?php

final class PhabricatorEditEngineConfigurationDisableController
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

    $type = PhabricatorEditEngineConfigurationTransaction::TYPE_DISABLE;

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorEditEngineConfigurationTransaction())
        ->setTransactionType($type)
        ->setNewValue(!$config->getIsDisabled());

      $editor = id(new PhabricatorEditEngineConfigurationEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true);

      $editor->applyTransactions($config, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($cancel_uri);
    }

    if ($config->getIsDisabled()) {
      $title = pht('Enable Form');
      $body = pht(
        'Enable this form? Users who can see it will be able to use it to '.
        'create objects.');
      $button = pht('Enable Form');
    } else {
      $title = pht('Disable Form');
      $body = pht(
        'Disable this form? Users will no longer be able to use it.');
      $button = pht('Disable Form');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitButton($button)
      ->addCancelbutton($cancel_uri);
  }

}
