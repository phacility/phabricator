<?php

final class DrydockAuthorizationAuthorizeController
  extends DrydockController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $authorization = id(new DrydockAuthorizationQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$authorization) {
      return new Aphront404Response();
    }

    $authorization_uri = $this->getApplicationURI("authorization/{$id}/");
    $is_authorize = ($request->getURIData('action') == 'authorize');

    $state_authorized = DrydockAuthorization::BLUEPRINTAUTH_AUTHORIZED;
    $state_declined = DrydockAuthorization::BLUEPRINTAUTH_DECLINED;

    $state = $authorization->getBlueprintAuthorizationState();
    $can_authorize = ($state != $state_authorized);
    $can_decline = ($state != $state_declined);

    if ($is_authorize && !$can_authorize) {
      return $this->newDialog()
        ->setTitle(pht('Already Authorized'))
        ->appendParagraph(
          pht(
            'This authorization has already been approved.'))
        ->addCancelButton($authorization_uri);
    }

    if (!$is_authorize && !$can_decline) {
      return $this->newDialog()
        ->setTitle(pht('Already Declined'))
        ->appendParagraph(
          pht('This authorization has already been declined.'))
        ->addCancelButton($authorization_uri);
    }

    if ($request->isFormPost()) {
      if ($is_authorize) {
        $new_state = $state_authorized;
      } else {
        $new_state = $state_declined;
      }

      $authorization
        ->setBlueprintAuthorizationState($new_state)
        ->save();

      return id(new AphrontRedirectResponse())->setURI($authorization_uri);
    }

    if ($is_authorize) {
      $title = pht('Approve Authorization');
      $body = pht(
        'Approve this authorization? The object will be able to lease and '.
        'allocate resources created by this blueprint.');
      $button = pht('Approve Authorization');
    } else {
      $title = pht('Decline Authorization');
      $body = pht(
        'Decline this authorization? The object will not be able to lease '.
        'or allocate resources created by this blueprint.');
      $button = pht('Decline Authorization');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitButton($button)
      ->addCancelButton($authorization_uri);
  }

}
