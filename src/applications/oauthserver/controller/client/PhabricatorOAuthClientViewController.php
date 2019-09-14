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
    $properties = $this->buildPropertyListView($client);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($client->getName())
      ->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $client,
      new PhabricatorOAuthServerTransactionQuery());
    $timeline->setShouldTerminate(true);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($properties);

    $title = pht('OAuth Application: %s', $client->getName());

    $curtain = $this->buildCurtain($client);

    $columns = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $box,
          $timeline,
        ));

    return $this->newPage()
      ->setCrumbs($crumbs)
      ->setTitle($title)
      ->appendChild($columns);
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

  private function buildCurtain(PhabricatorOAuthServerClient $client) {
    $viewer = $this->getViewer();
    $actions = array();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $client,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $client->getID();

    $actions[] = id(new PhabricatorActionView())
      ->setName(pht('Edit Application'))
      ->setIcon('fa-pencil')
      ->setWorkflow(!$can_edit)
      ->setDisabled(!$can_edit)
      ->setHref($client->getEditURI());

    $actions[] = id(new PhabricatorActionView())
      ->setName(pht('Show Application Secret'))
      ->setIcon('fa-eye')
      ->setHref($this->getApplicationURI("client/secret/{$id}/"))
      ->setDisabled(!$can_edit)
      ->setWorkflow(true);

    $is_disabled = $client->getIsDisabled();
    if ($is_disabled) {
      $disable_text = pht('Enable Application');
      $disable_icon = 'fa-check';
    } else {
      $disable_text = pht('Disable Application');
      $disable_icon = 'fa-ban';
    }

    $disable_uri = $this->getApplicationURI("client/disable/{$id}/");

    $actions[] = id(new PhabricatorActionView())
      ->setName($disable_text)
      ->setIcon($disable_icon)
      ->setWorkflow(true)
      ->setDisabled(!$can_edit)
      ->setHref($disable_uri);

    $actions[] = id(new PhabricatorActionView())
      ->setName(pht('Generate Test Token'))
      ->setIcon('fa-plus')
      ->setWorkflow(true)
      ->setHref($this->getApplicationURI("client/test/{$id}/"));

    $curtain = $this->newCurtainView($client);

    foreach ($actions as $action) {
      $curtain->addAction($action);
    }

    return $curtain;
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

    return $view;
  }
}
