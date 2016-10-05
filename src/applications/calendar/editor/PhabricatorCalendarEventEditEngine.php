<?php

final class PhabricatorCalendarEventEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'calendar.event';

  public function getEngineName() {
    return pht('Calendar Events');
  }

  public function getSummaryHeader() {
    return pht('Configure Calendar Event Forms');
  }

  public function getSummaryText() {
    return pht('Configure how users create and edit events.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  protected function newEditableObject() {
    return PhabricatorCalendarEvent::initializeNewCalendarEvent(
      $this->getViewer());
  }

  protected function newObjectQuery() {
    return new PhabricatorCalendarEventQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Event');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Event: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getMonogram();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Event');
  }

  protected function getObjectName() {
    return pht('Event');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('event/edit/');
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

    if ($this->getIsCreate()) {
      $invitee_phids = array($viewer->getPHID());
    } else {
      $invitee_phids = $object->getInviteePHIDsForEdit();
    }

    $frequency_options = array(
      PhutilCalendarRecurrenceRule::FREQUENCY_DAILY => pht('Daily'),
      PhutilCalendarRecurrenceRule::FREQUENCY_WEEKLY => pht('Weekly'),
      PhutilCalendarRecurrenceRule::FREQUENCY_MONTHLY => pht('Monthly'),
      PhutilCalendarRecurrenceRule::FREQUENCY_YEARLY => pht('Yearly'),
    );

    $fields = array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the event.'))
        ->setIsRequired(true)
        ->setTransactionType(
          PhabricatorCalendarEventNameTransaction::TRANSACTIONTYPE)
        ->setConduitDescription(pht('Rename the event.'))
        ->setConduitTypeDescription(pht('New event name.'))
        ->setValue($object->getName()),
      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setDescription(pht('Description of the event.'))
        ->setTransactionType(
          PhabricatorCalendarEventDescriptionTransaction::TRANSACTIONTYPE)
        ->setConduitDescription(pht('Update the event description.'))
        ->setConduitTypeDescription(pht('New event description.'))
        ->setValue($object->getDescription()),
      id(new PhabricatorBoolEditField())
        ->setKey('cancelled')
        ->setOptions(pht('Active'), pht('Cancelled'))
        ->setLabel(pht('Cancelled'))
        ->setDescription(pht('Cancel the event.'))
        ->setTransactionType(
          PhabricatorCalendarEventCancelTransaction::TRANSACTIONTYPE)
        ->setIsConduitOnly(true)
        ->setConduitDescription(pht('Cancel or restore the event.'))
        ->setConduitTypeDescription(pht('True to cancel the event.'))
        ->setValue($object->getIsCancelled()),
      id(new PhabricatorUsersEditField())
        ->setKey('hostPHID')
        ->setAliases(array('host'))
        ->setLabel(pht('Host'))
        ->setDescription(pht('Host of the event.'))
        ->setTransactionType(
          PhabricatorCalendarEventHostTransaction::TRANSACTIONTYPE)
        ->setIsConduitOnly($this->getIsCreate())
        ->setConduitDescription(pht('Change the host of the event.'))
        ->setConduitTypeDescription(pht('New event host.'))
        ->setSingleValue($object->getHostPHID()),
      id(new PhabricatorDatasourceEditField())
        ->setKey('inviteePHIDs')
        ->setAliases(array('invite', 'invitee', 'invitees', 'inviteePHID'))
        ->setLabel(pht('Invitees'))
        ->setDatasource(new PhabricatorMetaMTAMailableDatasource())
        ->setTransactionType(
          PhabricatorCalendarEventInviteTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('Users invited to the event.'))
        ->setConduitDescription(pht('Change invited users.'))
        ->setConduitTypeDescription(pht('New event invitees.'))
        ->setValue($invitee_phids)
        ->setCommentActionLabel(pht('Change Invitees')),
    );

    if ($this->getIsCreate()) {
      $fields[] = id(new PhabricatorBoolEditField())
        ->setKey('isRecurring')
        ->setLabel(pht('Recurring'))
        ->setOptions(pht('One-Time Event'), pht('Recurring Event'))
        ->setTransactionType(
          PhabricatorCalendarEventRecurringTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('One time or recurring event.'))
        ->setConduitDescription(pht('Make the event recurring.'))
        ->setConduitTypeDescription(pht('Mark the event as a recurring event.'))
        ->setValue($object->getIsRecurring());


      $rrule = $object->newRecurrenceRule();
      if ($rrule) {
        $frequency = $rrule->getFrequency();
      } else {
        $frequency = null;
      }

      $fields[] = id(new PhabricatorSelectEditField())
        ->setKey('frequency')
        ->setLabel(pht('Frequency'))
        ->setOptions($frequency_options)
        ->setTransactionType(
          PhabricatorCalendarEventFrequencyTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('Recurring event frequency.'))
        ->setConduitDescription(pht('Change the event frequency.'))
        ->setConduitTypeDescription(pht('New event frequency.'))
        ->setValue($frequency);
    }

    if ($this->getIsCreate() || $object->getIsRecurring()) {
      $fields[] = id(new PhabricatorEpochEditField())
        ->setAllowNull(true)
        ->setKey('until')
        ->setLabel(pht('Repeat Until'))
        ->setTransactionType(
          PhabricatorCalendarEventUntilDateTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('Last instance of the event.'))
        ->setConduitDescription(pht('Change when the event repeats until.'))
        ->setConduitTypeDescription(pht('New final event time.'))
        ->setValue($object->getUntilDateTimeEpoch());
    }

    $fields[] = id(new PhabricatorBoolEditField())
      ->setKey('isAllDay')
      ->setLabel(pht('All Day'))
      ->setOptions(pht('Normal Event'), pht('All Day Event'))
      ->setTransactionType(
        PhabricatorCalendarEventAllDayTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('Marks this as an all day event.'))
      ->setConduitDescription(pht('Make the event an all day event.'))
      ->setConduitTypeDescription(pht('Mark the event as an all day event.'))
      ->setValue($object->getIsAllDay());

    $fields[] = id(new PhabricatorEpochEditField())
      ->setKey('start')
      ->setLabel(pht('Start'))
      ->setTransactionType(
        PhabricatorCalendarEventStartDateTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('Start time of the event.'))
      ->setConduitDescription(pht('Change the start time of the event.'))
      ->setConduitTypeDescription(pht('New event start time.'))
      ->setValue($object->getStartDateTimeEpoch());

    $fields[] = id(new PhabricatorEpochEditField())
      ->setKey('end')
      ->setLabel(pht('End'))
      ->setTransactionType(
        PhabricatorCalendarEventEndDateTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('End time of the event.'))
      ->setConduitDescription(pht('Change the end time of the event.'))
      ->setConduitTypeDescription(pht('New event end time.'))
      ->setValue($object->getEndDateTimeEpoch());

    $fields[] = id(new PhabricatorIconSetEditField())
      ->setKey('icon')
      ->setLabel(pht('Icon'))
      ->setIconSet(new PhabricatorCalendarIconSet())
      ->setTransactionType(
        PhabricatorCalendarEventIconTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('Event icon.'))
      ->setConduitDescription(pht('Change the event icon.'))
      ->setConduitTypeDescription(pht('New event icon.'))
      ->setValue($object->getIcon());

    return $fields;
  }

  protected function willBuildEditForm($object, array $fields) {
    $all_day_field = idx($fields, 'isAllDay');
    $start_field = idx($fields, 'start');
    $end_field = idx($fields, 'end');

    if ($all_day_field) {
      $is_all_day = $all_day_field->getValueForTransaction();

      $control_ids = array();
      if ($start_field) {
        $control_ids[] = $start_field->getControlID();
      }
      if ($end_field) {
        $control_ids[] = $end_field->getControlID();
      }

      Javelin::initBehavior(
        'event-all-day',
        array(
          'allDayID' => $all_day_field->getControlID(),
          'controlIDs' => $control_ids,
        ));

    } else {
      $is_all_day = $object->getIsAllDay();
    }

    if ($is_all_day) {
      if ($start_field) {
        $start_field->setHideTime(true);
      }

      if ($end_field) {
        $end_field->setHideTime(true);
      }
    }



    return $fields;
  }
}
