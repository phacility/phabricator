<?php

final class DiffusionMirrorEditController
  extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($request->getURIData('id')) {
      $mirror = id(new PhabricatorRepositoryMirrorQuery())
        ->setViewer($viewer)
        ->withIDs(array($request->getURIData('id')))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$mirror) {
        return new Aphront404Response();
      }
      $is_new = false;
    } else {
      $mirror = PhabricatorRepositoryMirror::initializeNewMirror($viewer)
        ->setRepositoryPHID($repository->getPHID())
        ->attachRepository($repository);
      $is_new = true;
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/#mirrors');

    $v_remote = $mirror->getRemoteURI();
    $e_remote = true;

    $v_credentials = $mirror->getCredentialPHID();
    $e_credentials = null;

    $credentials = id(new PassphraseCredentialQuery())
      ->setViewer($viewer)
      ->withIsDestroyed(false)
      ->execute();

    $errors = array();
    if ($request->isFormPost()) {
      $v_remote = $request->getStr('remoteURI');
      if (strlen($v_remote)) {
        try {
          PhabricatorRepository::assertValidRemoteURI($v_remote);
          $e_remote = null;
        } catch (Exception $ex) {
          $e_remote = pht('Invalid');
          $errors[] = $ex->getMessage();
        }
      } else {
        $e_remote = pht('Required');
        $errors[] = pht('You must provide a remote URI.');
      }

      $v_credentials = $request->getStr('credential');
      if ($v_credentials) {
        $phids = mpull($credentials, null, 'getPHID');
        if (empty($phids[$v_credentials])) {
          $e_credentials = pht('Invalid');
          $errors[] = pht(
            'You do not have permission to use those credentials.');
        }
      }

      if (!$errors) {
        $mirror
          ->setRemoteURI($v_remote)
          ->setCredentialPHID($v_credentials)
          ->save();
        return id(new AphrontReloadResponse())->setURI($edit_uri);
      }
    }

    $form_errors = null;
    if ($errors) {
      $form_errors = id(new PHUIInfoView())
        ->setErrors($errors);
    }

    if ($is_new) {
      $title = pht('Create Mirror');
      $submit = pht('Create Mirror');
    } else {
      $title = pht('Edit Mirror');
      $submit = pht('Save Changes');
    }

    $form = id(new PHUIFormLayoutView())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Remote URI'))
          ->setName('remoteURI')
          ->setValue($v_remote)
          ->setError($e_remote))
      ->appendChild(
        id(new PassphraseCredentialControl())
          ->setLabel(pht('Credentials'))
          ->setName('credential')
          ->setAllowNull(true)
          ->setValue($v_credentials)
          ->setError($e_credentials)
          ->setOptions($credentials));

    return $this->newDialog()
      ->setTitle($title)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($form_errors)
      ->appendChild($form)
      ->addSubmitButton($submit)
      ->addCancelButton($edit_uri);
  }


}
