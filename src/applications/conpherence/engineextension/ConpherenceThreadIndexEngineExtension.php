<?php

final class ConpherenceThreadIndexEngineExtension
  extends PhabricatorIndexEngineExtension {

  const EXTENSIONKEY = 'conpherence.thread';

  public function getExtensionName() {
    return pht('Conpherence Threads');
  }

  public function shouldIndexObject($object) {
    return ($object instanceof ConpherenceThread);
  }

  public function indexObject(
    PhabricatorIndexEngine $engine,
    $object) {

    $force = $this->shouldForceFullReindex();

    if (!$force) {
      $xaction_phids = $this->getParameter('transactionPHIDs');
      if (!$xaction_phids) {
        return;
      }
    }

    $query = id(new ConpherenceTransactionQuery())
      ->setViewer($this->getViewer())
      ->withObjectPHIDs(array($object->getPHID()))
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT))
      ->needComments(true);

    if (!$force) {
      $query->withPHIDs($xaction_phids);
    }

    $xactions = $query->execute();

    if (!$xactions) {
      return;
    }

    foreach ($xactions as $xaction) {
      $this->indexComment($object, $xaction);
    }
  }

  private function indexComment(
    ConpherenceThread $thread,
    ConpherenceTransaction $xaction) {

    $pager = id(new AphrontCursorPagerView())
      ->setPageSize(1)
      ->setAfterID($xaction->getID());

    $previous_xactions = id(new ConpherenceTransactionQuery())
      ->setViewer($this->getViewer())
      ->withObjectPHIDs(array($thread->getPHID()))
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT))
      ->executeWithCursorPager($pager);
    $previous = head($previous_xactions);

    $index = id(new ConpherenceIndex())
      ->setThreadPHID($thread->getPHID())
      ->setTransactionPHID($xaction->getPHID())
      ->setPreviousTransactionPHID($previous ? $previous->getPHID() : null)
      ->setCorpus($xaction->getComment()->getContent());

    queryfx(
      $index->establishConnection('w'),
      'INSERT INTO %T
        (threadPHID, transactionPHID, previousTransactionPHID, corpus)
        VALUES (%s, %s, %ns, %s)
        ON DUPLICATE KEY UPDATE corpus = VALUES(corpus)',
      $index->getTableName(),
      $index->getThreadPHID(),
      $index->getTransactionPHID(),
      $index->getPreviousTransactionPHID(),
      $index->getCorpus());
  }

}
