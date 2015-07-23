<?php

abstract class PhabricatorCalendarController extends PhabricatorController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $actions = id(new PhabricatorActionListView())
      ->setUser($this->getViewer())
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Create Event'))
          ->setHref('/calendar/event/create/'))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Create Public Event'))
          ->setHref('/calendar/event/create/?mode=public'))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Create Recurring Event'))
          ->setHref('/calendar/event/create/?mode=recurring'));

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Event'))
        ->setHref($this->getApplicationURI().'event/create/')
        ->setIcon('fa-plus-square')
        ->setDropdownMenu($actions));

    return $crumbs;
  }

  protected function getEventAtIndexForGhostPHID($viewer, $phid, $index) {
    $result = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withInstanceSequencePairs(
        array(
          array(
            $phid,
            $index,
          ),
        ))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    return $result;
  }

  protected function createEventFromGhost($viewer, $event, $index) {
    $invitees = $event->getInvitees();

    $new_ghost = $event->generateNthGhost($index, $viewer);
    $new_ghost->attachParentEvent($event);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $new_ghost
      ->setID(null)
      ->setPHID(null)
      ->removeViewerTimezone($viewer)
      ->setViewPolicy($event->getViewPolicy())
      ->setEditPolicy($event->getEditPolicy())
      ->save();
    $ghost_invitees = array();
    foreach ($invitees as $invitee) {
      $ghost_invitee = clone $invitee;
      $ghost_invitee
        ->setID(null)
        ->setEventPHID($new_ghost->getPHID())
        ->save();
    }
    unset($unguarded);
    return $new_ghost;
  }
}
