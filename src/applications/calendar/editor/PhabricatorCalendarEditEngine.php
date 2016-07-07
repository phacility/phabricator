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
        ->setConduitDescription(pht('Rename the event.'))
        ->setConduitTypeDescription(pht('New event name.'))
        ->setTransactionType(PhabricatorCalendarEventTransaction::TYPE_NAME)
        ->setIsRequired(true)
        ->setValue($object->getName()),
    );

    return $fields;
  }

}
