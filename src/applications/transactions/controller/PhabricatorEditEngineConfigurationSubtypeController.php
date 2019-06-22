<?php

final class PhabricatorEditEngineConfigurationSubtypeController
  extends PhabricatorEditEngineController {

  public function handleRequest(AphrontRequest $request) {
    $engine_key = $request->getURIData('engineKey');
    $this->setEngineKey($engine_key);

    $key = $request->getURIData('key');
    $viewer = $this->getViewer();

    $config = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($viewer)
      ->withEngineKeys(array($engine_key))
      ->withIdentifiers(array($key))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$config) {
      return new Aphront404Response();
    }

    $cancel_uri = "/transactions/editengine/{$engine_key}/view/{$key}/";

    $engine = $config->getEngine();
    if (!$engine->supportsSubtypes()) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $xactions = array();

      $subtype = $request->getStr('subtype');
      $type_subtype = PhabricatorEditEngineSubtypeTransaction::TRANSACTIONTYPE;
      $xactions[] = id(new PhabricatorEditEngineConfigurationTransaction())
        ->setTransactionType($type_subtype)
        ->setNewValue($subtype);

      $editor = id(new PhabricatorEditEngineConfigurationEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true);

      $editor->applyTransactions($config, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($cancel_uri);
    }

    $fields = $engine->getFieldsForConfig($config);

    $help = pht(<<<EOTEXT
Choose the object **subtype** that this form should create and edit.
EOTEXT
      );

    $map = $engine->newSubtypeMap()->getDisplayMap();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions($help)
      ->appendControl(
        id(new AphrontFormSelectControl())
          ->setName('subtype')
          ->setLabel(pht('Subtype'))
          ->setValue($config->getSubtype())
          ->setOptions($map));

    return $this->newDialog()
      ->setTitle(pht('Change Form Subtype'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendForm($form)
      ->addSubmitButton(pht('Save Changes'))
      ->addCancelButton($cancel_uri);
  }

}
