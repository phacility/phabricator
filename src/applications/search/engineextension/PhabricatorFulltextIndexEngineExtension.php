<?php

final class PhabricatorFulltextIndexEngineExtension
  extends PhabricatorIndexEngineExtension {

  const EXTENSIONKEY = 'fulltext';

  public function getExtensionName() {
    return pht('Fulltext Engine');
  }

  public function getIndexVersion($object) {
    $version = array();

    if ($object instanceof PhabricatorApplicationTransactionInterface) {
      // If this is a normal object with transactions, we only need to
      // reindex it if there are new transactions (or comment edits).
      $version[] = $this->getTransactionVersion($object);
      $version[] = $this->getCommentVersion($object);
    }

    if (!$version) {
      return null;
    }

    return implode(':', $version);
  }

  public function shouldIndexObject($object) {
    return ($object instanceof PhabricatorFulltextInterface);
  }

  public function indexObject(
    PhabricatorIndexEngine $engine,
    $object) {

    $engine = $object->newFulltextEngine();
    if (!$engine) {
      return;
    }

    $engine->setObject($object);

    $engine->buildFulltextIndexes();
  }

  private function getTransactionVersion($object) {
    $xaction = $object->getApplicationTransactionTemplate();

    $xaction_row = queryfx_one(
      $xaction->establishConnection('r'),
      'SELECT id FROM %T WHERE objectPHID = %s
        ORDER BY id DESC LIMIT 1',
      $xaction->getTableName(),
      $object->getPHID());
    if (!$xaction_row) {
      return 'none';
    }

    return $xaction_row['id'];
  }

  private function getCommentVersion($object) {
    $xaction = $object->getApplicationTransactionTemplate();

    try {
      $comment = $xaction->getApplicationTransactionCommentObject();
      if (!$comment) {
        return 'none';
      }
    } catch (Exception $ex) {
      return 'none';
    }

    $comment_row = queryfx_one(
      $comment->establishConnection('r'),
      'SELECT c.id FROM %T x JOIN %T c
        ON x.phid = c.transactionPHID
        WHERE x.objectPHID = %s
        ORDER BY c.id DESC LIMIT 1',
      $xaction->getTableName(),
      $comment->getTableName(),
      $object->getPHID());
    if (!$comment_row) {
      return 'none';
    }

    return $comment_row['id'];
  }


}
