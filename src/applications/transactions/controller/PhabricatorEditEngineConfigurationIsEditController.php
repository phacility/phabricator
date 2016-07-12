<?php

final class PhabricatorEditEngineConfigurationIsEditController
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

    $type = PhabricatorEditEngineConfigurationTransaction::TYPE_ISEDIT;

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorEditEngineConfigurationTransaction())
        ->setTransactionType($type)
        ->setNewValue(!$config->getIsEdit());

      $editor = id(new PhabricatorEditEngineConfigurationEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true);

      $editor->applyTransactions($config, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($cancel_uri);
    }

    if ($config->getIsEdit()) {
      $title = pht('Unmark as Edit Form');
      $body = pht(
        'Unmark this form as an edit form? It will no longer be able to be '.
        'used to edit objects.');
      $button = pht('Unmark Form');
    } else {
      $title = pht('Mark as Edit Form');
      $body = pht(
        'Mark this form as an edit form? Users who can view it will be able '.
        'to use it to edit objects.');
      $button = pht('Mark Form');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitButton($button)
      ->addCancelbutton($cancel_uri);
  }

}
