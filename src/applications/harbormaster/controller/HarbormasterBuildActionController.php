<?php

final class HarbormasterBuildActionController
  extends HarbormasterController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');
    $via = $request->getURIData('via');

    $build = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$build) {
      return new Aphront404Response();
    }

    $xaction =
      HarbormasterBuildMessageTransaction::getTransactionObjectForMessageType(
        $action);

    if (!$xaction) {
      return new Aphront404Response();
    }

    switch ($via) {
      case 'buildable':
        $return_uri = '/'.$build->getBuildable()->getMonogram();
        break;
      default:
        $return_uri = $this->getApplicationURI('/build/'.$build->getID().'/');
        break;
    }

    try {
      $xaction->assertCanSendMessage($viewer, $build);
    } catch (HarbormasterMessageException $ex) {
      return $this->newDialog()
        ->setTitle($ex->getTitle())
        ->appendChild($ex->getBody())
        ->addCancelButton($return_uri);
    }

    if ($request->isDialogFormPost()) {
      $build->sendMessage($viewer, $xaction->getHarbormasterBuildMessageType());
      return id(new AphrontRedirectResponse())->setURI($return_uri);
    }

    $title = $xaction->newConfirmPromptTitle();
    $body = $xaction->newConfirmPromptBody();
    $submit = $xaction->getHarbormasterBuildMessageName();

    return $this->newDialog()
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($return_uri)
      ->addSubmitButton($submit);
  }

}
