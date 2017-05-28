<?php

final class ConpherenceEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'conpherence.thread';

  public function getEngineName() {
    return pht('Conpherence');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorConpherenceApplication';
  }

  public function getSummaryHeader() {
    return pht('Configure Conpherence Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing forms in Conpherence.');
  }

  protected function newEditableObject() {
    return ConpherenceThread::initializeNewRoom($this->getViewer());
  }

  protected function newObjectQuery() {
    return new ConpherenceThreadQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Room');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Room: %s', $object->getTitle());
  }

  protected function getObjectEditShortText($object) {
    return $object->getTitle();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Room');
  }

  protected function getObjectName() {
    return pht('Room');
  }

  protected function getObjectCreateCancelURI($object) {
    return $this->getApplication()->getApplicationURI('/');
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('edit/');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  public function isEngineConfigurable() {
    return false;
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

    if ($this->getIsCreate()) {
      $participant_phids = array($viewer->getPHID());
      $initial_phids = array();
    } else {
      $participant_phids = $object->getParticipantPHIDs();
      $initial_phids = $participant_phids;
    }

    // Only show participants on create or conduit, not edit
    $conduit_only = !$this->getIsCreate();

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Room name.'))
        ->setConduitTypeDescription(pht('New Room name.'))
        ->setIsRequired(true)
        ->setTransactionType(
          ConpherenceThreadTitleTransaction::TRANSACTIONTYPE)
        ->setValue($object->getTitle()),

      id(new PhabricatorTextEditField())
        ->setKey('topic')
        ->setLabel(pht('Topic'))
        ->setDescription(pht('Room topic.'))
        ->setConduitTypeDescription(pht('New Room topic.'))
        ->setTransactionType(
          ConpherenceThreadTopicTransaction::TRANSACTIONTYPE)
        ->setValue($object->getTopic()),

      id(new PhabricatorUsersEditField())
        ->setKey('participants')
        ->setValue($participant_phids)
        ->setInitialValue($initial_phids)
        ->setIsConduitOnly($conduit_only)
        ->setAliases(array('users', 'members', 'participants', 'userPHID'))
        ->setDescription(pht('Room participants.'))
        ->setUseEdgeTransactions(true)
        ->setConduitTypeDescription(pht('New Room participants.'))
        ->setTransactionType(
          ConpherenceThreadParticipantsTransaction::TRANSACTIONTYPE)
        ->setLabel(pht('Initial Participants')),

    );
  }

}
