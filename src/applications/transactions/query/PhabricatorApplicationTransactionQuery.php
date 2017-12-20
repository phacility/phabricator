<?php

abstract class PhabricatorApplicationTransactionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;
  private $objectPHIDs;
  private $authorPHIDs;
  private $transactionTypes;
  private $withComments;

  private $needComments = true;
  private $needHandles  = true;

  final public static function newQueryForObject(
    PhabricatorApplicationTransactionInterface $object) {

    $xaction = $object->getApplicationTransactionTemplate();
    $target_class = get_class($xaction);

    $queries = id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
    foreach ($queries as $query) {
      $query_xaction = $query->getTemplateApplicationTransaction();
      $query_class = get_class($query_xaction);

      if ($query_class === $target_class) {
        return id(clone $query);
      }
    }

    return null;
  }

  abstract public function getTemplateApplicationTransaction();

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function withTransactionTypes(array $transaction_types) {
    $this->transactionTypes = $transaction_types;
    return $this;
  }

  public function withComments($with_comments) {
    $this->withComments = $with_comments;
    return $this;
  }

  public function needComments($need) {
    $this->needComments = $need;
    return $this;
  }

  public function needHandles($need) {
    $this->needHandles = $need;
    return $this;
  }

  protected function loadPage() {
    $table = $this->getTemplateApplicationTransaction();

    $xactions = $this->loadStandardPage($table);

    foreach ($xactions as $xaction) {
      $xaction->attachViewer($this->getViewer());
    }

    if ($this->needComments) {
      $comment_phids = array_filter(mpull($xactions, 'getCommentPHID'));

      $comments = array();
      if ($comment_phids) {
        $comments =
          id(new PhabricatorApplicationTransactionTemplatedCommentQuery())
            ->setTemplate($table->getApplicationTransactionCommentObject())
            ->setViewer($this->getViewer())
            ->withPHIDs($comment_phids)
            ->execute();
        $comments = mpull($comments, null, 'getPHID');
      }

      foreach ($xactions as $xaction) {
        if ($xaction->getCommentPHID()) {
          $comment = idx($comments, $xaction->getCommentPHID());
          if ($comment) {
            $xaction->attachComment($comment);
          }
        }
      }
    } else {
      foreach ($xactions as $xaction) {
        $xaction->setCommentNotLoaded(true);
      }
    }

    return $xactions;
  }

  protected function willFilterPage(array $xactions) {
    $object_phids = array_keys(mpull($xactions, null, 'getObjectPHID'));

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($object_phids)
      ->execute();

    foreach ($xactions as $key => $xaction) {
      $object_phid = $xaction->getObjectPHID();
      if (empty($objects[$object_phid])) {
        unset($xactions[$key]);
        continue;
      }
      $xaction->attachObject($objects[$object_phid]);
    }

    // NOTE: We have to do this after loading objects, because the objects
    // may help determine which handles are required (for example, in the case
    // of custom fields).

    if ($this->needHandles) {
      $phids = array();
      foreach ($xactions as $xaction) {
        $phids[$xaction->getPHID()] = $xaction->getRequiredHandlePHIDs();
      }
      $handles = array();
      $merged = array_mergev($phids);
      if ($merged) {
        $handles = $this->getViewer()->loadHandles($merged);
        $handles = iterator_to_array($handles);
      }
      foreach ($xactions as $xaction) {
        $xaction->setHandles(
          array_select_keys(
            $handles,
            $phids[$xaction->getPHID()]));
      }
    }

    return $xactions;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'x.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'x.objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'x.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->transactionTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'x.transactionType IN (%Ls)',
        $this->transactionTypes);
    }

    if ($this->withComments !== null) {
      if (!$this->withComments) {
        $where[] = qsprintf(
          $conn,
          'c.id IS NULL');
      }
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->withComments !== null) {
      $xaction = $this->getTemplateApplicationTransaction();
      $comment = $xaction->getApplicationTransactionCommentObject();

      // Not every transaction type has comments, so we may be able to
      // implement this constraint trivially.

      if (!$comment) {
        if ($this->withComments) {
          throw new PhabricatorEmptyQueryException();
        } else {
          // If we're querying for transactions with no comments and the
          // transaction type does not support comments, we don't need to
          // do anything.
        }
      } else {
        if ($this->withComments) {
          $joins[] = qsprintf(
            $conn,
            'JOIN %T c ON x.phid = c.transactionPHID',
            $comment->getTableName());
        } else {
          $joins[] = qsprintf(
            $conn,
            'LEFT JOIN %T c ON x.phid = c.transactionPHID',
            $comment->getTableName());
        }
      }
    }

    return $joins;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->withComments !== null) {
      return true;
    }

    return parent::shouldGroupQueryResultRows();
  }

  public function getQueryApplicationClass() {
    // TODO: Sort this out?
    return null;
  }

  protected function getPrimaryTableAlias() {
    return 'x';
  }

}
