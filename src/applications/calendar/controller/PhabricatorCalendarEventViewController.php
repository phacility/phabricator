<?php

final class PhabricatorCalendarEventViewController
  extends PhabricatorCalendarController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $event = $this->loadEvent();
    if (!$event) {
      return new Aphront404Response();
    }

    // If we looked up or generated a stub event, redirect to that event's
    // canonical URI.
    $id = $request->getURIData('id');
    if ($event->getID() != $id) {
      $uri = $event->getURI();
      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $monogram = $event->getMonogram();
    $page_title = $monogram.' '.$event->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($monogram);
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $event,
      new PhabricatorCalendarEventTransactionQuery());

    $header = $this->buildHeaderView($event);
    $curtain = $this->buildCurtain($event);
    $details = $this->buildPropertySection($event);
    $description = $this->buildDescriptionView($event);

    $comment_view = id(new PhabricatorCalendarEventEditEngine())
      ->setViewer($viewer)
      ->buildEditEngineCommentView($event);

    $timeline->setQuoteRef($monogram);
    $comment_view->setTransactionTimeline($timeline);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setMainColumn(
        array(
          $timeline,
          $comment_view,
        ))
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $details)
      ->addPropertySection(pht('Description'), $description);

    return $this->newPage()
      ->setTitle($page_title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($event->getPHID()))
      ->appendChild($view);
  }

  private function buildHeaderView(
    PhabricatorCalendarEvent $event) {
    $viewer = $this->getViewer();
    $id = $event->getID();

    $is_cancelled = $event->getIsCancelled();
    $icon = $is_cancelled ? ('fa-ban') : ('fa-check');
    $color = $is_cancelled ? ('red') : ('bluegrey');
    $status = $is_cancelled ? pht('Cancelled') : pht('Active');

    $invite_status = $event->getUserInviteStatus($viewer->getPHID());
    $status_invited = PhabricatorCalendarEventInvitee::STATUS_INVITED;
    $is_invite_pending = ($invite_status == $status_invited);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($event->getName())
      ->setStatus($icon, $color, $status)
      ->setPolicyObject($event)
      ->setHeaderIcon('fa-calendar');

    if ($is_invite_pending) {
      $decline_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon('fa-times grey')
        ->setHref($this->getApplicationURI("/event/decline/{$id}/"))
        ->setWorkflow(true)
        ->setText(pht('Decline'));

      $accept_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon('fa-check green')
        ->setHref($this->getApplicationURI("/event/accept/{$id}/"))
        ->setWorkflow(true)
        ->setText(pht('Accept'));

      $header->addActionLink($decline_button)
        ->addActionLink($accept_button);
    }
    return $header;
  }

  private function buildCurtain(PhabricatorCalendarEvent $event) {
    $viewer = $this->getRequest()->getUser();
    $id = $event->getID();
    $is_cancelled = $event->isCancelledEvent();
    $is_attending = $event->getIsUserAttending($viewer->getPHID());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $event,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = "event/edit/{$id}/";
    if ($event->isChildEvent()) {
      $edit_label = pht('Edit This Instance');
    } else {
      $edit_label = pht('Edit Event');
    }

    $curtain = $this->newCurtainView($event);

    if ($edit_label && $edit_uri) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName($edit_label)
          ->setIcon('fa-pencil')
          ->setHref($this->getApplicationURI($edit_uri))
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));
    }

    if ($is_attending) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Decline Event'))
          ->setIcon('fa-user-times')
          ->setHref($this->getApplicationURI("event/join/{$id}/"))
          ->setWorkflow(true));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Join Event'))
          ->setIcon('fa-user-plus')
          ->setHref($this->getApplicationURI("event/join/{$id}/"))
          ->setWorkflow(true));
    }

    $cancel_uri = $this->getApplicationURI("event/cancel/{$id}/");
    $cancel_disabled = !$can_edit;

    if ($event->isChildEvent()) {
      $cancel_label = pht('Cancel This Instance');
      $reinstate_label = pht('Reinstate This Instance');

      if ($event->getParentEvent()->getIsCancelled()) {
        $cancel_disabled = true;
      }
    } else if ($event->isParentEvent()) {
      $cancel_label = pht('Cancel All');
      $reinstate_label = pht('Reinstate All');
    } else {
      $cancel_label = pht('Cancel Event');
      $reinstate_label = pht('Reinstate Event');
    }

    if ($is_cancelled) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName($reinstate_label)
          ->setIcon('fa-plus')
          ->setHref($cancel_uri)
          ->setDisabled($cancel_disabled)
          ->setWorkflow(true));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName($cancel_label)
          ->setIcon('fa-times')
          ->setHref($cancel_uri)
          ->setDisabled($cancel_disabled)
          ->setWorkflow(true));
    }

    return $curtain;
  }

  private function buildPropertySection(
    PhabricatorCalendarEvent $event) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    if ($event->getIsAllDay()) {
      $date_start = phabricator_date($event->getViewerDateFrom(), $viewer);
      $date_end = phabricator_date($event->getViewerDateTo(), $viewer);

      if ($date_start == $date_end) {
        $properties->addProperty(
          pht('Time'),
          phabricator_date($event->getViewerDateFrom(), $viewer));
      } else {
        $properties->addProperty(
          pht('Starts'),
          phabricator_date($event->getViewerDateFrom(), $viewer));
        $properties->addProperty(
          pht('Ends'),
          phabricator_date($event->getViewerDateTo(), $viewer));
      }
    } else {
      $properties->addProperty(
        pht('Starts'),
        phabricator_datetime($event->getViewerDateFrom(), $viewer));

      $properties->addProperty(
        pht('Ends'),
        phabricator_datetime($event->getViewerDateTo(), $viewer));
    }

    if ($event->getIsRecurring()) {
      $properties->addProperty(
        pht('Recurs'),
        ucwords(idx($event->getRecurrenceFrequency(), 'rule')));

      if ($event->getRecurrenceEndDate()) {
        $properties->addProperty(
          pht('Recurrence Ends'),
          phabricator_datetime($event->getRecurrenceEndDate(), $viewer));
      }

      if ($event->getInstanceOfEventPHID()) {
        $properties->addProperty(
          pht('Recurrence of Event'),
          pht('%s of %s',
            $event->getSequenceIndex(),
            $viewer->renderHandle($event->getInstanceOfEventPHID())->render()));
      }
    }

    $properties->addProperty(
      pht('Host'),
      $viewer->renderHandle($event->getUserPHID()));

    $invitees = $event->getInvitees();
    foreach ($invitees as $key => $invitee) {
      if ($invitee->isUninvited()) {
        unset($invitees[$key]);
      }
    }

    if ($invitees) {
      $invitee_list = new PHUIStatusListView();

      $icon_invited = PHUIStatusItemView::ICON_OPEN;
      $icon_attending = PHUIStatusItemView::ICON_ACCEPT;
      $icon_declined = PHUIStatusItemView::ICON_REJECT;

      $status_invited = PhabricatorCalendarEventInvitee::STATUS_INVITED;
      $status_attending = PhabricatorCalendarEventInvitee::STATUS_ATTENDING;
      $status_declined = PhabricatorCalendarEventInvitee::STATUS_DECLINED;

      $icon_map = array(
        $status_invited => $icon_invited,
        $status_attending => $icon_attending,
        $status_declined => $icon_declined,
      );

      $icon_color_map = array(
        $status_invited => null,
        $status_attending => 'green',
        $status_declined => 'red',
      );

      foreach ($invitees as $invitee) {
        $item = new PHUIStatusItemView();
        $invitee_phid = $invitee->getInviteePHID();
        $status = $invitee->getStatus();
        $target = $viewer->renderHandle($invitee_phid);
        $icon = $icon_map[$status];
        $icon_color = $icon_color_map[$status];

        $item->setIcon($icon, $icon_color)
          ->setTarget($target);
        $invitee_list->addItem($item);
      }
    } else {
      $invitee_list = phutil_tag(
        'em',
        array(),
        pht('None'));
    }

    $properties->addProperty(
      pht('Invitees'),
      $invitee_list);

    $properties->invokeWillRenderEvent();

    $properties->addProperty(
      pht('Icon'),
      id(new PhabricatorCalendarIconSet())
        ->getIconLabel($event->getIcon()));

    return $properties;
  }

  private function buildDescriptionView(
    PhabricatorCalendarEvent $event) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    if (strlen($event->getDescription())) {
      $description = new PHUIRemarkupView($viewer, $event->getDescription());
      $properties->addTextContent($description);
      return $properties;
    }

    return null;
  }


  private function loadEvent() {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $id = $request->getURIData('id');
    $sequence = $request->getURIData('sequence');

    // We're going to figure out which event you're trying to look at. Most of
    // the time this is simple, but you may be looking at an instance of a
    // recurring event which we haven't generated an object for.

    // If you are, we're going to generate a "stub" event so we have a real
    // ID and PHID to work with, since the rest of the infrastructure relies
    // on these identifiers existing.

    // Load the event identified by ID first.
    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$event) {
      return null;
    }

    // If we aren't looking at an instance of this event, this is a completely
    // normal request and we can just return this event.
    if (!$sequence) {
      return $event;
    }

    // When you view "E123/999", E123 is normally the parent event. However,
    // you might visit a different instance first instead and then fiddle
    // with the URI. If the event we're looking at is a child, we are going
    // to act on the parent instead.
    if ($event->isChildEvent()) {
      $event = $event->getParentEvent();
    }

    // Try to load the instance. If it already exists, we're all done and
    // can just return it.
    $instance = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withInstanceSequencePairs(
        array(
          array($event->getPHID(), $sequence),
        ))
      ->executeOne();
    if ($instance) {
      return $instance;
    }

    if (!$viewer->isLoggedIn()) {
      throw new Exception(
        pht(
          'This event instance has not been created yet. Log in to create '.
          'it.'));
    }

    $instance = $event->newStub($viewer, $sequence);

    return $instance;
  }

}
