<?php

final class PhabricatorAuthMessageViewController
  extends PhabricatorAuthMessageController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);

    $message = id(new PhabricatorAuthMessageQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$message) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($message->getObjectName())
      ->setBorder(true);

    $header = $this->buildHeaderView($message);
    $properties = $this->buildPropertiesView($message);
    $curtain = $this->buildCurtain($message);

    $timeline = $this->buildTransactionTimeline(
      $message,
      new PhabricatorAuthMessageTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $timeline,
        ))
      ->addPropertySection(pht('Details'), $properties);

    return $this->newPage()
      ->setTitle($message->getMessageTypeDisplayName())
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $message->getPHID(),
        ))
      ->appendChild($view);
  }

  private function buildHeaderView(PhabricatorAuthMessage $message) {
    $viewer = $this->getViewer();

    $view = id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader($message->getMessageTypeDisplayName());

    return $view;
  }

  private function buildPropertiesView(PhabricatorAuthMessage $message) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $view->addProperty(
      pht('Description'),
      $message->getMessageType()->getShortDescription());

    $view->addSectionHeader(
      pht('Message Preview'),
      PHUIPropertyListView::ICON_SUMMARY);

    $view->addTextContent(
      new PHUIRemarkupView($viewer, $message->getMessageText()));

    return $view;
  }

  private function buildCurtain(PhabricatorAuthMessage $message) {
    $viewer = $this->getViewer();
    $id = $message->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $message,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($message);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Message'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("message/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $curtain;
  }

}
