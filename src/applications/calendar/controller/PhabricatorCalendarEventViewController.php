<?php

final class PhabricatorCalendarEventViewController
  extends PhabricatorCalendarController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $sequence = $request->getURIData('sequence');

    $timeline = null;

    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$event) {
      return new Aphront404Response();
    }

    if ($sequence) {
      $result = $this->getEventAtIndexForGhostPHID(
        $viewer,
        $event->getPHID(),
        $sequence);

      if ($result) {
        $parent_event = $event;
        $event = $result;
        $event->attachParentEvent($parent_event);
        return id(new AphrontRedirectResponse())
          ->setURI('/E'.$result->getID());
      } else if ($sequence && $event->getIsRecurring()) {
        $parent_event = $event;
        $event = $event->generateNthGhost($sequence, $viewer);
        $event->attachParentEvent($parent_event);
      } else if ($sequence) {
        return new Aphront404Response();
      }

      $title = $event->getMonogram().' ('.$sequence.')';
      $page_title = $title.' '.$event->getName();
      $crumbs = $this->buildApplicationCrumbs();
      $crumbs->addTextCrumb($title, '/'.$event->getMonogram().'/'.$sequence);


    } else {
      $title = 'E'.$event->getID();
      $page_title = $title.' '.$event->getName();
      $crumbs = $this->buildApplicationCrumbs();
      $crumbs->addTextCrumb($title, '/E'.$event->getID());
    }

    if (!$event->getIsGhostEvent()) {
      $timeline = $this->buildTransactionTimeline(
        $event,
        new PhabricatorCalendarEventTransactionQuery());
    }

    $header = $this->buildHeaderView($event);
    $actions = $this->buildActionView($event);
    $properties = $this->buildPropertyView($event);

    $properties->setActionList($actions);
    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    $add_comment_header = $is_serious
      ? pht('Add Comment')
      : pht('Add To Plate');
    $draft = PhabricatorDraft::newFromUserAndKey($viewer, $event->getPHID());
    if ($sequence) {
      $comment_uri = $this->getApplicationURI(
        '/event/comment/'.$event->getID().'/'.$sequence.'/');
    } else {
      $comment_uri = $this->getApplicationURI(
        '/event/comment/'.$event->getID().'/');
    }
    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($event->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction($comment_uri)
      ->setSubmitButtonName(pht('Add Comment'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
        $add_comment_form,
      ),
      array(
        'title' => $page_title,
        'pageObjects' => array($event->getPHID()),
      ));
  }

  private function buildHeaderView(PhabricatorCalendarEvent $event) {
    $viewer = $this->getRequest()->getUser();
    $id = $event->getID();

    $is_cancelled = $event->getIsCancelled();
    $icon = $is_cancelled ? ('fa-times') : ('fa-calendar');
    $color = $is_cancelled ? ('grey') : ('green');
    $status = $is_cancelled ? pht('Cancelled') : pht('Active');

    $invite_status = $event->getUserInviteStatus($viewer->getPHID());
    $status_invited = PhabricatorCalendarEventInvitee::STATUS_INVITED;
    $is_invite_pending = ($invite_status == $status_invited);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($event->getName())
      ->setStatus($icon, $color, $status)
      ->setPolicyObject($event);

    if ($is_invite_pending) {
      $decline_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon(id(new PHUIIconView())
          ->setIconFont('fa-times grey'))
        ->setHref($this->getApplicationURI("/event/decline/{$id}/"))
        ->setWorkflow(true)
        ->setText(pht('Decline'));

      $accept_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon(id(new PHUIIconView())
          ->setIconFont('fa-check green'))
        ->setHref($this->getApplicationURI("/event/accept/{$id}/"))
        ->setWorkflow(true)
        ->setText(pht('Accept'));

      $header->addActionLink($decline_button)
        ->addActionLink($accept_button);
    }
    return $header;
  }

  private function buildActionView(PhabricatorCalendarEvent $event) {
    $viewer = $this->getRequest()->getUser();
    $id = $event->getID();
    $is_cancelled = $event->getIsCancelled();
    $is_attending = $event->getIsUserAttending($viewer->getPHID());

    $actions = id(new PhabricatorActionListView())
      ->setObjectURI($this->getApplicationURI('event/'.$id.'/'))
      ->setUser($viewer)
      ->setObject($event);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $event,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_label = false;
    $edit_uri = false;

    if ($event->getIsGhostEvent()) {
      $index = $event->getSequenceIndex();
      $edit_label = pht('Edit This Instance');
      $edit_uri = "event/edit/{$id}/{$index}/";
    } else if ($event->getIsRecurrenceException()) {
      $edit_label = pht('Edit This Instance');
      $edit_uri = "event/edit/{$id}/";
    } else {
      $edit_label = pht('Edit');
      $edit_uri = "event/edit/{$id}/";
    }

    if ($edit_label && $edit_uri) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName($edit_label)
          ->setIcon('fa-pencil')
          ->setHref($this->getApplicationURI($edit_uri))
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));
    }

    if ($is_attending) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Decline Event'))
          ->setIcon('fa-user-times')
          ->setHref($this->getApplicationURI("event/join/{$id}/"))
          ->setWorkflow(true));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Join Event'))
          ->setIcon('fa-user-plus')
          ->setHref($this->getApplicationURI("event/join/{$id}/"))
          ->setWorkflow(true));
    }

    $cancel_uri = $this->getApplicationURI("event/cancel/{$id}/");

    if ($event->getIsGhostEvent()) {
      $index = $event->getSequenceIndex();
      $can_reinstate = $event->getIsParentCancelled();

      $cancel_label = pht('Cancel This Instance');
      $reinstate_label = pht('Reinstate This Instance');
      $cancel_disabled = (!$can_edit || $can_reinstate);
      $cancel_uri = $this->getApplicationURI("event/cancel/{$id}/{$index}/");
    } else if ($event->getIsRecurrenceException()) {
      $can_reinstate = $event->getIsParentCancelled();
      $cancel_label = pht('Cancel This Instance');
      $reinstate_label = pht('Reinstate This Instance');
      $cancel_disabled = (!$can_edit || $can_reinstate);
    } else if ($event->getIsRecurrenceParent()) {
      $cancel_label = pht('Cancel Recurrence');
      $reinstate_label = pht('Reinstate Recurrence');
      $cancel_disabled = !$can_edit;
    } else {
      $cancel_label = pht('Cancel Event');
      $reinstate_label = pht('Reinstate Event');
      $cancel_disabled = !$can_edit;
    }

    if ($is_cancelled) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName($reinstate_label)
          ->setIcon('fa-plus')
          ->setHref($cancel_uri)
          ->setDisabled($cancel_disabled)
          ->setWorkflow(true));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName($cancel_label)
          ->setIcon('fa-times')
          ->setHref($cancel_uri)
          ->setDisabled($cancel_disabled)
          ->setWorkflow(true));
    }

    return $actions;
  }

  private function buildPropertyView(PhabricatorCalendarEvent $event) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($event);

    if ($event->getIsAllDay()) {
      $date_start = phabricator_date($event->getDateFrom(), $viewer);
      $date_end = phabricator_date($event->getDateTo(), $viewer);

      if ($date_start == $date_end) {
        $properties->addProperty(
          pht('Time'),
          phabricator_date($event->getDateFrom(), $viewer));
      } else {
        $properties->addProperty(
          pht('Starts'),
          phabricator_date($event->getDateFrom(), $viewer));
        $properties->addProperty(
          pht('Ends'),
          phabricator_date($event->getDateTo(), $viewer));
      }
    } else {
      $properties->addProperty(
        pht('Starts'),
        phabricator_datetime($event->getDateFrom(), $viewer));

      $properties->addProperty(
        pht('Ends'),
        phabricator_datetime($event->getDateTo(), $viewer));
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

    $icon_display = PhabricatorCalendarIcon::renderIconForChooser(
      $event->getIcon());
    $properties->addProperty(
      pht('Icon'),
      $icon_display);

    if (strlen($event->getDescription())) {
      $description = PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($event->getDescription()),
        'default',
        $viewer);

      $properties->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);

      $properties->addTextContent($description);
    }

    return $properties;
  }

}
