<?php

final class PhabricatorCalendarEditEngine
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
      $this->getViewer(),
      $mode = null);
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
    return $this->getApplication()->getApplicationURI('event/editpro/');
  }

  protected function buildCustomEditFields($object) {
    $fields = array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the event.'))
        ->setIsRequired(true)
        ->setTransactionType(PhabricatorCalendarEventTransaction::TYPE_NAME)
        ->setConduitDescription(pht('Rename the event.'))
        ->setConduitTypeDescription(pht('New event name.'))
        ->setValue($object->getName()),
      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setDescription(pht('Description of the event.'))
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION)
        ->setConduitDescription(pht('Update the event description.'))
        ->setConduitTypeDescription(pht('New event description.'))
        ->setValue($object->getDescription()),
      id(new PhabricatorBoolEditField())
        ->setKey('cancelled')
        ->setOptions(pht('Active'), pht('Cancelled'))
        ->setLabel(pht('Cancelled'))
        ->setDescription(pht('Cancel the event.'))
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_CANCEL)
        ->setIsConduitOnly(true)
        ->setConduitDescription(pht('Cancel or restore the event.'))
        ->setConduitTypeDescription(pht('True to cancel the event.'))
        ->setValue($object->getIsCancelled()),
      id(new PhabricatorIconSetEditField())
        ->setKey('icon')
        ->setLabel(pht('Icon'))
        ->setIconSet(new PhabricatorCalendarIconSet())
        ->setTransactionType(PhabricatorCalendarEventTransaction::TYPE_ICON)
        ->setDescription(pht('Event icon.'))
        ->setConduitDescription(pht('Change the event icon.'))
        ->setConduitTypeDescription(pht('New event icon.'))
        ->setValue($object->getIcon()),
    );

    return $fields;
  }

}
