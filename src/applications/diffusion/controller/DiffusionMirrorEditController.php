<?php

final class DiffusionMirrorEditController
  extends DiffusionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    parent::willProcessRequest($data);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    if ($this->id) {
      $mirror = id(new PhabricatorRepositoryMirrorQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
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
        $e_remote = null;
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
      $form_errors = id(new AphrontErrorView())
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

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($form_errors)
      ->appendChild($form)
      ->addSubmitButton($submit)
      ->addCancelButton($edit_uri);

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }


}
