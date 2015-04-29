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

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => $page_title,
      ));
  }

  private function buildHeaderView(PhabricatorCalendarEvent $event) {
    $viewer = $this->getRequest()->getUser();
    $is_cancelled = $event->getIsCancelled();
    $icon = $is_cancelled ? ('fa-times') : ('fa-calendar');
    $color = $is_cancelled ? ('grey') : ('green');
    $status = $is_cancelled ? ('Cancelled') : ('Active');

    return id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($event->getName())
      ->setStatus($icon, $color, $status)
      ->setPolicyObject($event);
  }

  private function buildActionView(PhabricatorCalendarEvent $event) {
    $viewer = $this->getRequest()->getUser();
    $id = $event->getID();
    $is_cancelled = $event->getIsCancelled();

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
      $item = new PHUIStatusItemView();
      $invitee_phid = $invitee->getInviteePHID();
      $target = $viewer->renderHandle($invitee_phid);
      $item->setTarget($target);
      $invitee_list->addItem($item);
    }

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
