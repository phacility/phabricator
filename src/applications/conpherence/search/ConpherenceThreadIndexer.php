<?php

final class ConpherenceThreadIndexer
  extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new ConpherenceThread();
  }

  protected function loadDocumentByPHID($phid) {
    $object = id(new ConpherenceThreadQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($phid))
      ->executeOne();

    if (!$object) {
      throw new Exception(pht('No thread "%s" exists!', $phid));
    }

    return $object;
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $thread = $this->loadDocumentByPHID($phid);

    // NOTE: We're explicitly not building a document here, only rebuilding
    // the Conpherence search index.

    $context = nonempty($this->getContext(), array());
    $comment_phids = idx($context, 'commentPHIDs');

    if (is_array($comment_phids) && !$comment_phids) {
      // If this property is set, but empty, the transaction did not
      // include any chat text. For example, a user might have left the
      // conversation.
      return null;
    }

    $query = id(new ConpherenceTransactionQuery())
      ->setViewer($this->getViewer())
      ->withObjectPHIDs(array($thread->getPHID()))
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT))
      ->needComments(true);

    if ($comment_phids !== null) {
      $query->withPHIDs($comment_phids);
    }

    $xactions = $query->execute();

    foreach ($xactions as $xaction) {
      $this->indexComment($thread, $xaction);
    }

    return null;
  }

  private function indexComment(
    ConpherenceThread $thread,
    ConpherenceTransaction $xaction) {

    $previous = id(new ConpherenceTransactionQuery())
      ->setViewer($this->getViewer())
      ->withObjectPHIDs(array($thread->getPHID()))
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT))
      ->setAfterID($xaction->getID())
      ->setLimit(1)
      ->executeOne();

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
