<?php

final class DrydockAuthorizationViewController
  extends DrydockController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $authorization = id(new DrydockAuthorizationQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$authorization) {
      return new Aphront404Response();
    }

    $id = $authorization->getID();
    $title = pht('Authorization %d', $id);

    $blueprint = $authorization->getBlueprint();
    $blueprint_id = $blueprint->getID();

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($authorization);

    $state = $authorization->getBlueprintAuthorizationState();
    $icon = DrydockAuthorization::getBlueprintStateIcon($state);
    $name = DrydockAuthorization::getBlueprintStateName($state);

    $header->setStatus($icon, null, $name);

    $curtain = $this->buildCurtain($authorization);
    $properties = $this->buildPropertyListView($authorization);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Blueprints'),
      $this->getApplicationURI('blueprint/'));
    $crumbs->addTextCrumb(
      $blueprint->getBlueprintName(),
      $this->getApplicationURI("blueprint/{$blueprint_id}/"));
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addPropertySection(pht('Properties'), $properties);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));

  }

  private function buildCurtain(DrydockAuthorization $authorization) {
    $viewer = $this->getViewer();
    $id = $authorization->getID();

    $curtain = $this->newCurtainView($authorization);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $authorization,
      PhabricatorPolicyCapability::CAN_EDIT);

    $authorize_uri = $this->getApplicationURI("authorization/{$id}/authorize/");
    $decline_uri = $this->getApplicationURI("authorization/{$id}/decline/");

    $state_authorized = DrydockAuthorization::BLUEPRINTAUTH_AUTHORIZED;
    $state_declined = DrydockAuthorization::BLUEPRINTAUTH_DECLINED;

    $state = $authorization->getBlueprintAuthorizationState();
    $can_authorize = $can_edit && ($state != $state_authorized);
    $can_decline = $can_edit && ($state != $state_declined);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setHref($authorize_uri)
        ->setName(pht('Approve Authorization'))
        ->setIcon('fa-check')
        ->setWorkflow(true)
        ->setDisabled(!$can_authorize));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setHref($decline_uri)
        ->setName(pht('Decline Authorization'))
        ->setIcon('fa-times')
        ->setWorkflow(true)
        ->setDisabled(!$can_decline));

    return $curtain;
  }

  private function buildPropertyListView(DrydockAuthorization $authorization) {
    $viewer = $this->getViewer();

    $object_phid = $authorization->getObjectPHID();
    $handles = $viewer->loadHandles(array($object_phid));
    $handle = $handles[$object_phid];

    $view = new PHUIPropertyListView();

    $view->addProperty(
      pht('Authorized Object'),
      $handle->renderLink($handle->getFullName()));

    $view->addProperty(pht('Object Type'), $handle->getTypeName());

    $object_state = $authorization->getObjectAuthorizationState();

    $view->addProperty(
      pht('Authorization State'),
      DrydockAuthorization::getObjectStateName($object_state));

    return $view;
  }

}
