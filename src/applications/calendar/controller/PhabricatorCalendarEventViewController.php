<?php

final class PhabricatorCalendarEventViewController
  extends PhabricatorCalendarController {

  private $id;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$event) {
      return new Aphront404Response();
    }

    $title = 'E'.$event->getID();
    $page_title = $title.' '.$event->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title, '/E'.$event->getID());

    $timeline = $this->buildTransactionTimeline(
      $event,
      new PhabricatorCalendarEventTransactionQuery());

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
    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($event->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction(
        $this->getApplicationURI('/event/comment/'.$event->getID().'/'))
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
      ));
  }

  private function buildHeaderView(PhabricatorCalendarEvent $event) {
    $viewer = $this->getRequest()->getUser();
    $id = $event->getID();

    $is_cancelled = $event->getIsCancelled();
    $icon = $is_cancelled ? ('fa-times') : ('fa-calendar');
    $color = $is_cancelled ? ('grey') : ('green');
    $status = $is_cancelled ? ('Cancelled') : ('Active');

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

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Event'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("event/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

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

    if ($is_cancelled) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Reinstate Event'))
          ->setIcon('fa-plus')
          ->setHref($this->getApplicationURI("event/cancel/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Cancel Event'))
          ->setIcon('fa-times')
          ->setHref($this->getApplicationURI("event/cancel/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    return $actions;
  }

  private function buildPropertyView(PhabricatorCalendarEvent $event) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($event);

    $properties->addProperty(
      pht('Starts'),
      phabricator_datetime($event->getDateFrom(), $viewer));

    $properties->addProperty(
      pht('Ends'),
      phabricator_datetime($event->getDateTo(), $viewer));

    $invitees = $event->getInvitees();
    $invitee_list = new PHUIStatusListView();
    foreach ($invitees as $invitee) {
      if ($invitee->isUninvited()) {
        continue;
      }
      $item = new PHUIStatusItemView();
      $invitee_phid = $invitee->getInviteePHID();
      $target = $viewer->renderHandle($invitee_phid);
      $item->setNote($invitee->getStatus())
        ->setTarget($target);
      $invitee_list->addItem($item);
    }

    $properties->addProperty(
      pht('Host'),
      $viewer->renderHandle($event->getUserPHID()));

    $properties->addProperty(
      pht('Invitees'),
      $invitee_list);

    $properties->invokeWillRenderEvent();

    $properties->addSectionHeader(
      pht('Description'),
      PHUIPropertyListView::ICON_SUMMARY);
    $properties->addTextContent($event->getDescription());

    return $properties;
  }

}
