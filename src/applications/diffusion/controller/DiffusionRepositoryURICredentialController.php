<?php

final class DiffusionRepositoryURICredentialController
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

    $is_builtin = $uri->isBuiltin();
    $has_credential = (bool)$uri->getCredentialPHID();
    $view_uri = $uri->getViewURI();
    $is_remove = ($request->getURIData('action') == 'remove');

    if ($is_builtin) {
      return $this->newDialog()
        ->setTitle(pht('Builtin URIs Do Not Use Credentials'))
        ->appendParagraph(
          pht(
            'You can not set a credential for builtin URIs which this '.
            'server hosts. These URIs are not fetched from or pushed to, '.
            'and credentials are not required to authenticate any '.
            'activity against them.'))
        ->addCancelButton($view_uri);
    }

    if ($request->isFormPost()) {
      $xactions = array();

      if ($is_remove) {
        $new_phid = null;
      } else {
        $new_phid = $request->getStr('credentialPHID');
      }

      $type_credential = PhabricatorRepositoryURITransaction::TYPE_CREDENTIAL;

      $xactions[] = id(new PhabricatorRepositoryURITransaction())
        ->setTransactionType($type_credential)
        ->setNewValue($new_phid);

      $editor = id(new DiffusionURIEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($uri, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    $command_engine = $uri->newCommandEngine();
    $is_supported = $command_engine->isCredentialSupported();

    $body = null;
    $form = null;
    $width = AphrontDialogView::WIDTH_DEFAULT;
    if ($is_remove) {
      if ($has_credential) {
        $title = pht('Remove Credential');
        $body = pht(
          'This credential will no longer be used to authenticate activity '.
          'against this URI.');
        $button = pht('Remove Credential');
      } else {
        $title = pht('No Credential');
        $body = pht(
          'This URI does not have an associated credential.');
        $button = null;
      }
    } else if (!$is_supported) {
      $title = pht('Unauthenticated Protocol');
      $body = pht(
        'The protocol for this URI ("%s") does not use authentication, so '.
        'you can not provide a credential.',
        $command_engine->getDisplayProtocol());
      $button = null;
    } else {
      $effective_uri = $uri->getEffectiveURI();

      $label = $command_engine->getPassphraseCredentialLabel();
      $credential_type = $command_engine->getPassphraseDefaultCredentialType();

      $provides_type = $command_engine->getPassphraseProvidesCredentialType();
      $options = id(new PassphraseCredentialQuery())
        ->setViewer($viewer)
        ->withIsDestroyed(false)
        ->withProvidesTypes(array($provides_type))
        ->execute();

      $control = id(new PassphraseCredentialControl())
        ->setName('credentialPHID')
        ->setLabel($label)
        ->setValue($uri->getCredentialPHID())
        ->setCredentialType($credential_type)
        ->setOptions($options);

      $default_user = $effective_uri->getUser();
      if (strlen($default_user)) {
        $control->setDefaultUsername($default_user);
      }

      $form = id(new AphrontFormView())
        ->setViewer($viewer)
        ->appendControl($control);

      if ($has_credential) {
        $title = pht('Update Credential');
        $button = pht('Update Credential');
      } else {
        $title = pht('Set Credential');
        $button = pht('Set Credential');
      }

      $width = AphrontDialogView::WIDTH_FORM;
    }

    $dialog = $this->newDialog()
      ->setWidth($width)
      ->setTitle($title)
      ->addCancelButton($view_uri);

    if ($body) {
      $dialog->appendParagraph($body);
    }

    if ($form) {
      $dialog->appendForm($form);
    }

    if ($button) {
      $dialog->addSubmitButton($button);
    }

    return $dialog;
  }

}
