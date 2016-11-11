<?php

final class PhabricatorCalendarImportEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Calendar Imports');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this import.', $author);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {
    $actor = $this->getActor();

    // We import events when you create a source, or if you later reload it
    // explicitly.
    $should_reload = $this->getIsNewObject();

    // We adjust the import trigger if you change the import frequency or
    // disable the import.
    $should_trigger = false;

    foreach ($xactions as $xaction) {
      $xaction_type = $xaction->getTransactionType();
      switch ($xaction_type) {
        case PhabricatorCalendarImportReloadTransaction::TRANSACTIONTYPE:
          $should_reload = true;
          break;
        case PhabricatorCalendarImportFrequencyTransaction::TRANSACTIONTYPE:
          $should_trigger = true;
          break;
        case PhabricatorCalendarImportDisableTransaction::TRANSACTIONTYPE:
          $should_trigger = true;
          break;
      }
    }

    if ($should_reload) {
      $import_engine = $object->getEngine();
      $import_engine->importEventsFromSource($actor, $object, true);
    }

    if ($should_trigger) {
      $trigger_phid = $object->getTriggerPHID();
      if ($trigger_phid) {
        $trigger = id(new PhabricatorWorkerTriggerQuery())
          ->setViewer($actor)
          ->withPHIDs(array($trigger_phid))
          ->executeOne();

        if ($trigger) {
          $engine = new PhabricatorDestructionEngine();
          $engine->destroyObject($trigger);
        }
      }

      $frequency = $object->getTriggerFrequency();
      $now = PhabricatorTime::getNow();
      switch ($frequency) {
        case PhabricatorCalendarImport::FREQUENCY_ONCE:
          $clock = null;
          break;
        case PhabricatorCalendarImport::FREQUENCY_HOURLY:
          $clock = new PhabricatorMetronomicTriggerClock(
            array(
              'period' => phutil_units('1 hour in seconds'),
            ));
          break;
        case PhabricatorCalendarImport::FREQUENCY_DAILY:
          $clock = new PhabricatorDailyRoutineTriggerClock(
            array(
              'start' => $now,
            ));
          break;
        default:
          throw new Exception(
            pht(
              'Unknown import trigger frequency "%s".',
              $frequency));
      }

      // If the object has been disabled, don't write a new trigger.
      if ($object->getIsDisabled()) {
        $clock = null;
      }

      if ($clock) {
        $trigger_action = new PhabricatorScheduleTaskTriggerAction(
          array(
            'class' => 'PhabricatorCalendarImportReloadWorker',
            'data' => array(
              'importPHID' => $object->getPHID(),
              'via' => PhabricatorCalendarImportReloadWorker::VIA_TRIGGER,
            ),
            'options' => array(
              'objectPHID' => $object->getPHID(),
              'priority' => PhabricatorWorker::PRIORITY_BULK,
            ),
          ));

        $trigger_phid = PhabricatorPHID::generateNewPHID(
          PhabricatorWorkerTriggerPHIDType::TYPECONST);

        $object
          ->setTriggerPHID($trigger_phid)
          ->save();

        $trigger = id(new PhabricatorWorkerTrigger())
          ->setClock($clock)
          ->setAction($trigger_action)
          ->setPHID($trigger_phid)
          ->save();
      } else {
        $object
          ->setTriggerPHID(null)
          ->save();
      }
    }

    return $xactions;
  }


}
