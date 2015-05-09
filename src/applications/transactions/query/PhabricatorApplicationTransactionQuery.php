<?php

abstract class PhabricatorApplicationTransactionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;
  private $objectPHIDs;
  private $authorPHIDs;
  private $transactionTypes;

  private $needComments = true;
  private $needHandles  = true;

  abstract public function getTemplateApplicationTransaction();

  protected function buildMoreWhereClauses(AphrontDatabaseConnection $conn_r) {
    return array();
  }

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
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T x %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $xactions = $table->loadAllFromArray($data);

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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->objectPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->transactionTypes) {
      $where[] = qsprintf(
        $conn_r,
        'transactionType IN (%Ls)',
        $this->transactionTypes);
    }

    foreach ($this->buildMoreWhereClauses($conn_r) as $clause) {
      $where[] = $clause;
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }


  public function getQueryApplicationClass() {
    // TODO: Sort this out?
    return null;
  }

}
