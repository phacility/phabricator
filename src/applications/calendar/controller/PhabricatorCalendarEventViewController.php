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

    $start = $event->newStartDateTime()
      ->newPHPDateTime();

    $crumbs->addTextCrumb(
      $start->format('F Y'),
      '/calendar/query/month/'.$start->format('Y/m/'));

    $crumbs->addTextCrumb(
      $start->format('D jS'),
      '/calendar/query/month/'.$start->format('Y/m/d/'));

    $crumbs->addTextCrumb($monogram);
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $event,
      new PhabricatorCalendarEventTransactionQuery());

    $header = $this->buildHeaderView($event);
    $subheader = $this->buildSubheaderView($event);
    $curtain = $this->buildCurtain($event);
    $details = $this->buildPropertySection($event);
    $recurring = $this->buildRecurringSection($event);
    $description = $this->buildDescriptionView($event);

    $comment_view = id(new PhabricatorCalendarEventEditEngine())
      ->setViewer($viewer)
      ->buildEditEngineCommentView($event);

    $timeline->setQuoteRef($monogram);
    $comment_view->setTransactionTimeline($timeline);

    $details_header = id(new PHUIHeaderView())
      ->setHeader(pht('Details'));
    $recurring_header = $this->buildRecurringHeader($event);

    // NOTE: This is a bit hacky: for imported events, we're just hiding the
    // comment form without actually preventing comments. Users could still
    // submit a request to add comments to these events. This isn't really a
    // major problem since they can't do anything truly bad and there isn't an
    // easy way to selectively disable this or some other similar behaviors
    // today, but it would probably be nice to fully disable these
    // "pseudo-edits" (like commenting and probably subscribing and awarding
    // tokens) at some point.
    if ($event->isImportedEvent()) {
      $comment_view = null;
      $timeline->setShouldTerminate(true);
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setSubheader($subheader)
      ->setMainColumn(
        array(
          $timeline,
          $comment_view,
        ))
      ->setCurtain($curtain)
      ->addPropertySection(pht('Description'), $description)
      ->addPropertySection($recurring_header, $recurring)
      ->addPropertySection($details_header, $details);

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

    if ($event->getIsCancelled()) {
      $icon = 'fa-ban';
      $color = 'red';
      $status = pht('Cancelled');
    } else {
      $icon = 'fa-check';
      $color = 'bluegrey';
      $status = pht('Active');
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($event->getName())
      ->setStatus($icon, $color, $status)
      ->setPolicyObject($event)
      ->setHeaderIcon($event->getIcon());

    if ($event->isImportedEvent()) {
      $header->addTag(
        id(new PHUITagView())
          ->setType(PHUITagView::TYPE_SHADE)
          ->setName(pht('Imported'))
          ->setIcon('fa-download')
          ->setHref($event->getImportSource()->getURI())
          ->setShade('orange'));
    }

    foreach ($this->buildRSVPActions($event) as $action) {
      $header->addActionLink($action);
    }

    $options = PhabricatorCalendarEventInvitee::getAvailabilityMap();

    $is_attending = $event->getIsUserAttending($viewer->getPHID());
    if ($is_attending) {
      $invitee = $event->getInviteeForPHID($viewer->getPHID());

      $selected = $invitee->getDisplayAvailability($event);
      if (!$selected) {
        $selected = PhabricatorCalendarEventInvitee::AVAILABILITY_AVAILABLE;
      }

      $selected_option = idx($options, $selected);

      $availability_select = id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon('fa-circle '.$selected_option['color'])
        ->setText(pht('Availability: %s', $selected_option['name']));

      $dropdown = id(new PhabricatorActionListView())
        ->setUser($viewer);

      foreach ($options as $key => $option) {
        $uri = "event/availability/{$id}/{$key}/";
        $uri = $this->getApplicationURI($uri);

        $dropdown->addAction(
          id(new PhabricatorActionView())
            ->setName($option['name'])
            ->setIcon('fa-circle '.$option['color'])
            ->setHref($uri)
            ->setWorkflow(true));
      }

      $availability_select->setDropdownMenu($dropdown);
      $header->addActionLink($availability_select);
    }

    return $header;
  }

  private function buildCurtain(PhabricatorCalendarEvent $event) {
    $viewer = $this->getRequest()->getUser();
    $id = $event->getID();
    $is_attending = $event->getIsUserAttending($viewer->getPHID());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $event,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = "event/edit/{$id}/";
    $edit_uri = $this->getApplicationURI($edit_uri);
    $is_recurring = $event->getIsRecurring();
    $edit_label = pht('Edit Event');

    $curtain = $this->newCurtainView($event);

    if ($edit_label && $edit_uri) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName($edit_label)
          ->setIcon('fa-pencil')
          ->setHref($edit_uri)
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit || $is_recurring));
    }

    $recurring_uri = "{$edit_uri}page/recurring/";
    $can_recurring = $can_edit && !$event->isChildEvent();

    if ($event->getIsRecurring()) {
      $recurring_label = pht('Edit Recurrence');
    } else {
      $recurring_label = pht('Make Recurring');
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($recurring_label)
        ->setIcon('fa-repeat')
        ->setHref($recurring_uri)
        ->setDisabled(!$can_recurring)
        ->setWorkflow(true));

    $can_attend = !$event->isImportedEvent();

    if ($is_attending) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Decline Event'))
          ->setIcon('fa-user-times')
          ->setHref($this->getApplicationURI("event/join/{$id}/"))
          ->setDisabled(!$can_attend)
          ->setWorkflow(true));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Join Event'))
          ->setIcon('fa-user-plus')
          ->setHref($this->getApplicationURI("event/join/{$id}/"))
          ->setDisabled(!$can_attend)
          ->setWorkflow(true));
    }

    $cancel_uri = $this->getApplicationURI("event/cancel/{$id}/");
    $cancel_disabled = !$can_edit;

    $cancel_label = pht('Cancel Event');
    $reinstate_label = pht('Reinstate Event');

    if ($event->getIsCancelled()) {
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

    $ics_name = $event->getICSFilename();
    $export_uri = $this->getApplicationURI("event/export/{$id}/{$ics_name}");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Export as .ics'))
        ->setIcon('fa-download')
        ->setHref($export_uri));

    return $curtain;
  }

  private function buildPropertySection(
    PhabricatorCalendarEvent $event) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

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

      $viewer_phid = $viewer->getPHID();
      $is_rsvp_invited = $event->isRSVPInvited($viewer_phid);
      $type_user = PhabricatorPeopleUserPHIDType::TYPECONST;

      $head = array();
      $tail = array();
      foreach ($invitees as $invitee) {
        $item = new PHUIStatusItemView();
        $invitee_phid = $invitee->getInviteePHID();
        $status = $invitee->getStatus();
        $target = $viewer->renderHandle($invitee_phid);
        $is_user = (phid_get_type($invitee_phid) == $type_user);

        if (!$is_user) {
          $icon = 'fa-users';
          $icon_color = 'blue';
        } else {
          $icon = $icon_map[$status];
          $icon_color = $icon_color_map[$status];
        }

        // Highlight invited groups which you're a member of if you have
        // not RSVP'd to an event yet.
        if ($is_rsvp_invited) {
          if ($invitee_phid != $viewer_phid) {
            if ($event->hasRSVPAuthority($viewer_phid, $invitee_phid)) {
              $item->setHighlighted(true);
            }
          }
        }

        $item->setIcon($icon, $icon_color)
          ->setTarget($target);

        if ($is_user) {
          $tail[] = $item;
        } else {
          $head[] = $item;
        }
      }

      foreach (array_merge($head, $tail) as $item) {
        $invitee_list->addItem($item);
      }
    } else {
      $invitee_list = phutil_tag(
        'em',
        array(),
        pht('None'));
    }

    if ($event->isImportedEvent()) {
      $properties->addProperty(
        pht('Imported By'),
        pht(
          '%s from %s',
          $viewer->renderHandle($event->getImportAuthorPHID()),
          $viewer->renderHandle($event->getImportSourcePHID())));
    }

    $properties->addProperty(
      pht('Invitees'),
      $invitee_list);

    $properties->invokeWillRenderEvent();

    return $properties;
  }

  private function buildRecurringHeader(PhabricatorCalendarEvent $event) {
    $viewer = $this->getViewer();

    if (!$event->getIsRecurring()) {
      return null;
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recurring Event'));

    $sequence = $event->getSequenceIndex();
    if ($event->isParentEvent()) {
      $parent = $event;
    } else {
      $parent = $event->getParentEvent();
    }

    if ($parent->isValidSequenceIndex($viewer, $sequence + 1)) {
      $next_uri = $parent->getURI().'/'.($sequence + 1);
      $has_next = true;
    } else {
      $next_uri = null;
      $has_next = false;
    }

    if ($sequence) {
      if ($sequence > 1) {
        $previous_uri = $parent->getURI().'/'.($sequence - 1);
      } else {
        $previous_uri = $parent->getURI();
      }
      $has_previous = true;
    } else {
      $has_previous = false;
      $previous_uri = null;
    }

    $prev_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-chevron-left')
      ->setHref($previous_uri)
      ->setDisabled(!$has_previous)
      ->setText(pht('Previous'));

    $next_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-chevron-right')
      ->setHref($next_uri)
      ->setDisabled(!$has_next)
      ->setText(pht('Next'));

    $header
      ->addActionLink($next_button)
      ->addActionLink($prev_button);

    return $header;
  }

  private function buildRecurringSection(PhabricatorCalendarEvent $event) {
    $viewer = $this->getViewer();

    if (!$event->getIsRecurring()) {
      return null;
    }

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $is_parent = $event->isParentEvent();
    if ($is_parent) {
      $parent_link = null;
    } else {
      $parent = $event->getParentEvent();
      $parent_link = $viewer
        ->renderHandle($parent->getPHID())
        ->render();
    }

    $rrule = $event->newRecurrenceRule();

    if ($rrule) {
      $frequency = $rrule->getFrequency();
    } else {
      $frequency = null;
    }

    switch ($frequency) {
      case PhutilCalendarRecurrenceRule::FREQUENCY_DAILY:
        if ($is_parent) {
          $message = pht('This event repeats every day.');
        } else {
          $message = pht(
            'This event is an instance of %s, and repeats every day.',
            $parent_link);
        }
        break;
      case PhutilCalendarRecurrenceRule::FREQUENCY_WEEKLY:
        if ($is_parent) {
          $message = pht('This event repeats every week.');
        } else {
          $message = pht(
            'This event is an instance of %s, and repeats every week.',
            $parent_link);
        }
        break;
      case PhutilCalendarRecurrenceRule::FREQUENCY_MONTHLY:
        if ($is_parent) {
          $message = pht('This event repeats every month.');
        } else {
          $message = pht(
            'This event is an instance of %s, and repeats every month.',
            $parent_link);
        }
        break;
      case PhutilCalendarRecurrenceRule::FREQUENCY_YEARLY:
        if ($is_parent) {
          $message = pht('This event repeats every year.');
        } else {
          $message = pht(
            'This event is an instance of %s, and repeats every year.',
            $parent_link);
        }
        break;
    }

    $properties->addProperty(pht('Event Series'), $message);

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
      ->needRSVPs(array($viewer->getPHID()))
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

    if (!$event->isValidSequenceIndex($viewer, $sequence)) {
      return null;
    }

    return $event->newStub($viewer, $sequence);
  }

  private function buildSubheaderView(PhabricatorCalendarEvent $event) {
    $viewer = $this->getViewer();

    $host_phid = $event->getHostPHID();

    $handles = $viewer->loadHandles(array($host_phid));
    $handle = $handles[$host_phid];

    $host = $viewer->renderHandle($host_phid);
    $host = phutil_tag('strong', array(), $host);

    $image_uri = $handles[$host_phid]->getImageURI();
    $image_href = $handles[$host_phid]->getURI();

    $date = $event->renderEventDate($viewer, true);

    $content = pht('Hosted by %s on %s.', $host, $date);

    return id(new PHUIHeadThingView())
      ->setImage($image_uri)
      ->setImageHref($image_href)
      ->setContent($content);
  }


  private function buildRSVPActions(PhabricatorCalendarEvent $event) {
    $viewer = $this->getViewer();
    $id = $event->getID();

    $is_pending = $event->isRSVPInvited($viewer->getPHID());
    if (!$is_pending) {
      return array();
    }

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

    return array($decline_button, $accept_button);
  }

}
