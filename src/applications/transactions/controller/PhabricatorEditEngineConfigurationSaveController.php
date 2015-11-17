<?php

final class PhabricatorEditEngineConfigurationSaveController
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
      ->executeOne();
    if (!$config) {
      return id(new Aphront404Response());
    }

    $view_uri = $config->getURI();

    if ($config->getID()) {
      return $this->newDialog()
        ->setTitle(pht('Already Editable'))
        ->appendParagraph(
          pht('This form configuration is already editable.'))
        ->addCancelButton($view_uri);
    }

    if ($request->isFormPost()) {
      $editor = id(new PhabricatorEditEngineConfigurationEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      $editor->applyTransactions($config, array());

      return id(new AphrontRedirectResponse())
        ->setURI($config->getURI());
    }

    // TODO: Explain what this means in more detail once the implications are
    // more clear, or just link to some docs or something.

    return $this->newDialog()
      ->setTitle(pht('Make Builtin Editable'))
      ->appendParagraph(
        pht('Make this builtin form editable?'))
      ->addSubmitButton(pht('Make Editable'))
      ->addCancelButton($view_uri);
  }

}
