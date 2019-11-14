<?php

final class DiffusionIdentityViewController
  extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');
    $identity = id(new PhabricatorRepositoryIdentityQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$identity) {
      return new Aphront404Response();
    }

    $title = pht('Identity %d', $identity->getID());

    $curtain = $this->buildCurtain($identity);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($identity->getIdentityShortName())
      ->setHeaderIcon('fa-globe');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Identities'),
      $this->getApplicationURI('identity/'));
    $crumbs->addTextCrumb($identity->getObjectName());
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $identity,
      new PhabricatorRepositoryIdentityTransactionQuery());
    $timeline->setShouldTerminate(true);

    $properties = $this->buildPropertyList($identity);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
          $properties,
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));
  }

  private function buildCurtain(PhabricatorRepositoryIdentity $identity) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $identity,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $identity->getID();
    $edit_uri = $this->getApplicationURI("identity/edit/{$id}/");

    $curtain = $this->newCurtainView($identity);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Identity'))
        ->setHref($edit_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $curtain;
  }

  private function buildPropertyList(
    PhabricatorRepositoryIdentity $identity) {

    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $properties->addProperty(
      pht('Email Address'),
      $identity->getEmailAddress());

    $effective_phid = $identity->getCurrentEffectiveUserPHID();
    $automatic_phid = $identity->getAutomaticGuessedUserPHID();
    $manual_phid = $identity->getManuallySetUserPHID();

    if ($effective_phid) {
      $tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor('green')
        ->setIcon('fa-check')
        ->setName('Assigned');
    } else {
      $tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor('indigo')
        ->setIcon('fa-bomb')
        ->setName('Unassigned');
    }
    $properties->addProperty(
      pht('Effective User'),
      $this->buildPropertyValue($effective_phid));
    $properties->addProperty(
      pht('Automatically Detected User'),
      $this->buildPropertyValue($automatic_phid));
    $properties->addProperty(
      pht('Assigned To'),
      $this->buildPropertyValue($manual_phid));

    $header = id(new PHUIHeaderView())
      ->setHeader(array(pht('Identity Assignments'), $tag));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($properties);
  }

  private function buildPropertyValue($value) {
    $viewer = $this->getViewer();

    if ($value == DiffusionIdentityUnassignedDatasource::FUNCTION_TOKEN) {
      return phutil_tag('em', array(), pht('Explicitly Unassigned'));
    } else if (!$value) {
      return phutil_tag('em', array(), pht('None'));
    } else {
      return $viewer->renderHandle($value);
    }
  }
}
