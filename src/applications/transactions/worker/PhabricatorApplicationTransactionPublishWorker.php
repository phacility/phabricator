<?php

/**
 * Performs backgroundable work after applying transactions.
 *
 * This class handles email, notifications, feed stories, search indexes, and
 * other similar backgroundable work.
 */
final class PhabricatorApplicationTransactionPublishWorker
  extends PhabricatorWorker {


  /**
   * Publish information about a set of transactions.
   */
  protected function doWork() {
    $object = $this->loadObject();
    $editor = $this->buildEditor($object);
    $xactions = $this->loadTransactions($object);

    $editor->publishTransactions($object, $xactions);
  }


  /**
   * Load the object the transactions affect.
   */
  private function loadObject() {
    $data = $this->getTaskData();
    $viewer = PhabricatorUser::getOmnipotentUser();

    $phid = idx($data, 'objectPHID');
    if (!$phid) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Task has no object PHID!'));
    }

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$object) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Unable to load object with PHID "%s"!',
          $phid));
    }

    return $object;
  }


  /**
   * Build and configure an Editor to publish these transactions.
   */
  private function buildEditor(
    PhabricatorApplicationTransactionInterface $object) {
    $data = $this->getTaskData();

    $daemon_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_DAEMON,
      array());

    $viewer = PhabricatorUser::getOmnipotentUser();
    $editor = $object->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setContentSource($daemon_source)
      ->setActingAsPHID(idx($data, 'actorPHID'))
      ->loadWorkerState(idx($data, 'state', array()));

    return $editor;
  }


  /**
   * Load the transactions to be published.
   */
  private function loadTransactions(
    PhabricatorApplicationTransactionInterface $object) {
    $data = $this->getTaskData();

    $xaction_phids = idx($data, 'xactionPHIDs');
    if (!$xaction_phids) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Task has no transaction PHIDs!'));
    }

    $viewer = PhabricatorUser::getOmnipotentUser();

    $type = phid_get_subtype(head($xaction_phids));
    $xactions = $this->buildTransactionQuery($type)
      ->setViewer($viewer)
      ->withPHIDs($xaction_phids)
      ->needComments(true)
      ->execute();
    $xactions = mpull($xactions, null, 'getPHID');

    $missing = array_diff($xaction_phids, array_keys($xactions));
    if ($missing) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Unable to load transactions: %s.',
          implode(', ', $missing)));
    }

    return array_select_keys($xactions, $xaction_phids);
  }


  /**
   * Build a new transaction query of the appropriate class so we can load
   * the transactions.
   */
  private function buildTransactionQuery($type) {
    $queries = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorApplicationTransactionQuery')
      ->loadObjects();

    foreach ($queries as $query) {
      $query_type = $query
        ->getTemplateApplicationTransaction()
        ->getApplicationTransactionType();
      if ($query_type == $type) {
        return $query;
      }
    }

    throw new PhabricatorWorkerPermanentFailureException(
      pht(
        'Unable to load query for transaction type "%s"!',
        $type));
  }

}
