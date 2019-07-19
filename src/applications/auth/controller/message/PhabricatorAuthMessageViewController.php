<?php

final class PhabricatorAuthMessageViewController
  extends PhabricatorAuthMessageController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);

    // The "id" in the URI may either be an actual storage record ID (if a
    // message has already been created) or a message type key (for a message
    // type which does not have a record yet).

    // This flow allows messages which have not been set yet to have a detail
    // page (so users can get detailed information about the message and see
    // any default value).

    $id = $request->getURIData('id');
    if (ctype_digit($id)) {
      $message = id(new PhabricatorAuthMessageQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->executeOne();
      if (!$message) {
        return new Aphront404Response();
      }
    } else {
      $types = PhabricatorAuthMessageType::getAllMessageTypes();
      if (!isset($types[$id])) {
        return new Aphront404Response();
      }

      // If this message type already has a storage record, redirect to the
      // canonical page for the record.
      $message = id(new PhabricatorAuthMessageQuery())
        ->setViewer($viewer)
        ->withMessageKeys(array($id))
        ->executeOne();
      if ($message) {
        $message_uri = $message->getURI();
        return id(new AphrontRedirectResponse())->setURI($message_uri);
      }

      // Otherwise, create an empty placeholder message object with the
      // appropriate message type.
      $message = PhabricatorAuthMessage::initializeNewMessage($types[$id]);
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($message->getMessageType()->getDisplayName())
      ->setBorder(true);

    $header = $this->buildHeaderView($message);
    $properties = $this->buildPropertiesView($message);
    $curtain = $this->buildCurtain($message);

    if ($message->getID()) {
      $timeline = $this->buildTransactionTimeline(
        $message,
        new PhabricatorAuthMessageTransactionQuery());
      $timeline->setShouldTerminate(true);
    } else {
      $timeline = null;
    }

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

    $message_type = $message->getMessageType();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $full_description = $message_type->getFullDescription();
    if (strlen($full_description)) {
      $view->addTextContent(new PHUIRemarkupView($viewer, $full_description));
    } else {
      $short_description = $message_type->getShortDescription();
      $view->addProperty(pht('Description'), $short_description);
    }

    $message_text = $message->getMessageText();
    if (strlen($message_text)) {
      $view->addSectionHeader(
        pht('Message Preview'),
        PHUIPropertyListView::ICON_SUMMARY);

      $view->addTextContent(new PHUIRemarkupView($viewer, $message_text));
    }

    $default_text = $message_type->getDefaultMessageText();
    if (strlen($default_text)) {
      $view->addSectionHeader(
        pht('Default Message'),
        PHUIPropertyListView::ICON_SUMMARY);

      $view->addTextContent(new PHUIRemarkupView($viewer, $default_text));
    }

    return $view;
  }

  private function buildCurtain(PhabricatorAuthMessage $message) {
    $viewer = $this->getViewer();
    $id = $message->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $message,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($id) {
      $edit_uri = urisprintf('message/edit/%s/', $id);
      $edit_name = pht('Edit Message');
    } else {
      $edit_uri = urisprintf('message/edit/');
      $params = array(
        'messageKey' => $message->getMessageKey(),
      );
      $edit_uri = new PhutilURI($edit_uri, $params);

      $edit_name = pht('Customize Message');
    }
    $edit_uri = $this->getApplicationURI($edit_uri);

    $curtain = $this->newCurtainView($message);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($edit_name)
        ->setIcon('fa-pencil')
        ->setHref($edit_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $curtain;
  }

}
