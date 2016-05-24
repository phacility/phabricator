<?php

final class DiffusionRepositoryURIDisableController
  extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $id = $request->getURIData('id');
    $uri = id(new PhabricatorRepositoryURIQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->withRepositories(array($repository))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$uri) {
      return new Aphront404Response();
    }

    $is_disabled = $uri->getIsDisabled();
    $view_uri = $uri->getViewURI();

    if ($uri->isBuiltin()) {
      return $this->newDialog()
        ->setTitle(pht('Builtin URI'))
        ->appendParagraph(
          pht(
            'You can not manually disable builtin URIs. To hide a builtin '.
            'URI, configure its "Display" behavior instead.'))
        ->addCancelButton($view_uri);
    }

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorRepositoryURITransaction())
        ->setTransactionType(PhabricatorRepositoryURITransaction::TYPE_DISABLE)
        ->setNewValue(!$is_disabled);

      $editor = id(new DiffusionURIEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($uri, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($is_disabled) {
      $title = pht('Enable URI');
      $body = pht(
        'Enable this URI? Any configured behaviors will begin working '.
        'again.');
      $button = pht('Enable URI');
    } else {
      $title = pht('Disable URI');
      $body = pht(
        'Disable this URI? It will no longer be observed, fetched, mirrored, '.
        'served or shown to users.');
      $button = pht('Disable URI');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addCancelButton($view_uri)
      ->addSubmitButton($button);
  }

}
