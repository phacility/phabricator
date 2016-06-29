<?php

final class PhabricatorConduitTokenTerminateController
  extends PhabricatorConduitController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $object_phid = $request->getStr('objectPHID');
    $id = $request->getURIData('id');

    if ($id) {
      $token = id(new PhabricatorConduitTokenQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->withExpired(false)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$token) {
        return new Aphront404Response();
      }

      $tokens = array($token);
      $object_phid = $token->getObjectPHID();

      $title = pht('Terminate API Token');
      $body = pht(
        'Really terminate this token? Any system using this token '.
        'will no longer be able to make API requests.');
      $submit_button = pht('Terminate Token');
    } else {
      $tokens = id(new PhabricatorConduitTokenQuery())
        ->setViewer($viewer)
        ->withObjectPHIDs(array($object_phid))
        ->withExpired(false)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->execute();

      $title = pht('Terminate API Tokens');
      $body = pht(
        'Really terminate all active API tokens? Any systems using these '.
        'tokens will no longer be able to make API requests.');
      $submit_button = pht('Terminate Tokens');
    }

    if ($object_phid != $viewer->getPHID()) {
      $object = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($object_phid))
        ->executeOne();
      if (!$object) {
        return new Aphront404Response();
      }
    } else {
      $object = $viewer;
    }

    $panel_uri = id(new PhabricatorConduitTokensSettingsPanel())
      ->setViewer($viewer)
      ->setUser($object)
      ->getPanelURI();

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $panel_uri);

    if (!$tokens) {
      return $this->newDialog()
        ->setTitle(pht('No Tokens to Terminate'))
        ->appendParagraph(
          pht('There are no API tokens to terminate.'))
        ->addCancelButton($panel_uri);
    }

    if ($request->isFormPost()) {
      foreach ($tokens as $token) {
        $token
          ->setExpires(PhabricatorTime::getNow() - 60)
          ->save();
      }
      return id(new AphrontRedirectResponse())->setURI($panel_uri);
    }

    return $this->newDialog()
      ->setTitle($title)
      ->addHiddenInput('objectPHID', $object_phid)
      ->appendParagraph($body)
      ->addSubmitButton($submit_button)
      ->addCancelButton($panel_uri);
  }

}
