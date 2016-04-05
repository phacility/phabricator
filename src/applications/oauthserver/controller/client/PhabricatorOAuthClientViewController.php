<?php

final class PhabricatorOAuthClientViewController
  extends PhabricatorOAuthClientController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $client = id(new PhabricatorOAuthServerClientQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$client) {
      return new Aphront404Response();
    }

    $header = $this->buildHeaderView($client);
    $actions = $this->buildActionView($client);
    $properties = $this->buildPropertyListView($client);
    $properties->setActionList($actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($client->getName());

    $timeline = $this->buildTransactionTimeline(
      $client,
      new PhabricatorOAuthServerTransactionQuery());
    $timeline->setShouldTerminate(true);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $title = pht('OAuth Application: %s', $client->getName());

    return $this->newPage()
      ->setCrumbs($crumbs)
      ->setTitle($title)
      ->appendChild(
        array(
          $box,
          $timeline,
        ));
  }

  private function buildHeaderView(PhabricatorOAuthServerClient $client) {
    $viewer = $this->getViewer();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader(pht('OAuth Application: %s', $client->getName()))
      ->setPolicyObject($client);

    if ($client->getIsDisabled()) {
      $header->setStatus('fa-ban', 'indigo', pht('Disabled'));
    } else {
      $header->setStatus('fa-check', 'green', pht('Enabled'));
    }

    return $header;
  }

  private function buildActionView(PhabricatorOAuthServerClient $client) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $client,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $client->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Application'))
        ->setIcon('fa-pencil')
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit)
        ->setHref($client->getEditURI()));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Show Application Secret'))
        ->setIcon('fa-eye')
        ->setHref($this->getApplicationURI("client/secret/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    $is_disabled = $client->getIsDisabled();
    if ($is_disabled) {
      $disable_text = pht('Enable Application');
      $disable_icon = 'fa-check';
    } else {
      $disable_text = pht('Disable Application');
      $disable_icon = 'fa-ban';
    }

    $disable_uri = $this->getApplicationURI("client/disable/{$id}/");

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($disable_text)
        ->setIcon($disable_icon)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit)
        ->setHref($disable_uri));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Generate Test Token'))
        ->setIcon('fa-plus')
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI("client/test/{$id}/")));

    return $view;
  }

  private function buildPropertyListView(PhabricatorOAuthServerClient $client) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $view->addProperty(
      pht('Client PHID'),
      $client->getPHID());

    $view->addProperty(
      pht('Redirect URI'),
      $client->getRedirectURI());

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($client->getDateCreated(), $viewer));

    return $view;
  }
}
