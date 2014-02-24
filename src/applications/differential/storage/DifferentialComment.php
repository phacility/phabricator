<?php

/**
 * Temporary proxy shell around ApplicationTransactions.
 */
final class DifferentialComment
  implements PhabricatorMarkupInterface {

  const METADATA_ADDED_REVIEWERS   = 'added-reviewers';
  const METADATA_REMOVED_REVIEWERS = 'removed-reviewers';
  const METADATA_ADDED_CCS         = 'added-ccs';
  const METADATA_DIFF_ID           = 'diff-id';

  const MARKUP_FIELD_BODY          = 'markup:body';

  private $arbitraryDiffForFacebook;
  private $proxyComment;
  private $proxy;

  public function __construct() {
    $this->proxy = new DifferentialTransaction();
  }

  public function __clone() {
    $this->proxy = clone $this->proxy;
    if ($this->proxyComment) {
      $this->proxyComment = clone $this->proxyComment;
    }
  }

  public static function newFromModernTransaction(
    DifferentialTransaction $xaction) {

    $obj = new DifferentialComment();
    $obj->proxy = $xaction;

    if ($xaction->hasComment()) {
      $obj->proxyComment = $xaction->getComment();
    }

    return $obj;
  }

  public function getPHID() {
    return $this->proxy->getPHID();
  }

  public function getContent() {
    return $this->getProxyComment()->getContent();
  }

  public function setContent($content) {
    $this->getProxyComment()->setContent($content);
    return $this;
  }

  public function getAuthorPHID() {
    return $this->proxy->getAuthorPHID();
  }

  public function setAuthorPHID($author_phid) {
    $this->proxy->setAuthorPHID($author_phid);
    return $this;
  }

  public function setContentSource($content_source) {
    $this->proxy->setContentSource($content_source);
    $this->proxyComment->setContentSource($content_source);
    return $this;
  }

  public function setAction($action) {
    $meta = array();
    switch ($action) {
      case DifferentialAction::ACTION_COMMENT:
        $type = PhabricatorTransactions::TYPE_COMMENT;
        $old = null;
        $new = null;
        break;
      case DifferentialAction::ACTION_ADDREVIEWERS:
        $type = PhabricatorTransactions::TYPE_EDGE;
        $old = array();
        $new = array();
        $meta = array(
          'edge:type' => PhabricatorEdgeConfig::TYPE_DREV_HAS_REVIEWER,
        );
        break;
      case DifferentialAction::ACTION_ADDCCS:
        $type = PhabricatorTransactions::TYPE_SUBSCRIBERS;
        $old = array();
        $new = array();
        break;
      case DifferentialAction::ACTION_UPDATE:
        $type = DifferentialTransaction::TYPE_UPDATE;
        $old = null;
        $new = null;
        break;
      case DifferentialTransaction::TYPE_INLINE:
        $type = $action;
        $old = null;
        $new = null;
        break;
      default:
        $type = DifferentialTransaction::TYPE_ACTION;
        $old = null;
        $new = $action;
        break;
    }

    $xaction = $this->proxy;

    $xaction
      ->setTransactionType($type)
      ->setOldValue($old)
      ->setNewValue($new);

    if ($meta) {
      foreach ($meta as $key => $value) {
        $xaction->setMetadataValue($key, $value);
      }
    }

    return $this;
  }

  public function getAction() {
    switch ($this->proxy->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return DifferentialAction::ACTION_ADDCCS;
      case DifferentialTransaction::TYPE_UPDATE:
        return DifferentialAction::ACTION_UPDATE;
      case PhabricatorTransactions::TYPE_EDGE:
        return DifferentialAction::ACTION_ADDREVIEWERS;
      case PhabricatorTransactions::TYPE_COMMENT:
        return DifferentialAction::ACTION_COMMENT;
      case DifferentialTransaction::TYPE_INLINE:
        return DifferentialTransaction::TYPE_INLINE;
      default:
        return $this->proxy->getNewValue();
    }
  }

  public function setMetadata(array $metadata) {
    if (!$this->proxy->getTransactionType()) {
      throw new Exception(pht('Call setAction() before setMetadata()!'));
    }

    $key_cc = self::METADATA_ADDED_CCS;
    $key_add_rev = self::METADATA_ADDED_REVIEWERS;
    $key_rem_rev = self::METADATA_REMOVED_REVIEWERS;
    $key_diff_id = self::METADATA_DIFF_ID;

    switch ($this->proxy->getTransactionType()) {
      case DifferentialTransaction::TYPE_UPDATE:
        $id = idx($metadata, $key_diff_id);
        $this->proxy->setNewValue($id);
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        $rem = idx($metadata, $key_rem_rev, array());
        $old = array();
        foreach ($rem as $phid) {
          $old[$phid] = array(
            'src' => $this->proxy->getObjectPHID(),
            'type' => PhabricatorEdgeConfig::TYPE_DREV_HAS_REVIEWER,
            'dst' => $phid,
          );
        }
        $this->proxy->setOldValue($old);

        $add = idx($metadata, $key_add_rev, array());
        $new = array();
        foreach ($add as $phid) {
          $new[$phid] = array(
            'src' => $this->proxy->getObjectPHID(),
            'type' => PhabricatorEdgeConfig::TYPE_DREV_HAS_REVIEWER,
            'dst' => $phid,
          );
        }
        $this->proxy->setNewValue($new);
        break;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $phids = idx($metadata, $key_cc, array());
        $new = array();
        foreach ($phids as $phid) {
          $new[$phid] = $phid;
        }
        $this->proxy->setNewValue($new);
        break;
      default:
        break;
    }

    return $this;
  }

  public function getMetadata() {
    if (!$this->proxy->getTransactionType()) {
      throw new Exception(pht('Call setAction() before getMetadata()!'));
    }

    $key_cc = self::METADATA_ADDED_CCS;
    $key_add_rev = self::METADATA_ADDED_REVIEWERS;
    $key_rem_rev = self::METADATA_REMOVED_REVIEWERS;
    $key_diff_id = self::METADATA_DIFF_ID;

    $type = $this->proxy->getTransactionType();

    switch ($type) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $value = $this->proxy->getNewValue();
        if (!$value) {
          $value = array();
        }
        return array(
          $key_cc => $value,
        );
      case DifferentialTransaction::TYPE_UPDATE:
        return array(
          $key_diff_id => $this->proxy->getNewValue(),
        );
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $old = $this->proxy->getOldValue();
        if (!$old) {
          $old = array();
        }
        $new = $this->proxy->getNewValue();
        if (!$new) {
          $new = array();
        }

        $rem = array_diff_key($old, $new);
        $add = array_diff_key($new, $old);

        if ($type == PhabricatorTransactions::TYPE_EDGE) {
          return array(
            $key_add_rev => array_keys($add),
            $key_rem_rev => array_keys($rem),
          );
        } else {
          return array(
            $key_cc => array_keys($add),
          );
        }
      default:
        return array();
    }
  }

  public function getContentSource() {
    return $this->proxy->getContentSource();
  }

  private function getProxyComment() {
    if (!$this->proxyComment) {
      $this->proxyComment = new DifferentialTransactionComment();
    }
    return $this->proxyComment;
  }

  public function setProxyComment(DifferentialTransactionComment $proxy) {
    $this->proxyComment = $proxy;
    $this->proxy->attachComment($proxy);
    return $this;
  }

  public function setRevision(DifferentialRevision $revision) {
    $this->getProxyComment()->setRevisionPHID($revision->getPHID());
    $this->proxy->setObjectPHID($revision->getPHID());
    return $this;
  }

  public function giveFacebookSomeArbitraryDiff(DifferentialDiff $diff) {
    $this->arbitraryDiffForFacebook = $diff;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    switch ($this->proxy->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_EDGE:
        return array_merge(
          array_keys($this->proxy->getOldValue()),
          array_keys($this->proxy->getNewValue()));
    }

    return array();
  }

  public function getMarkupFieldKey($field) {
    return 'DC:'.$this->getPHID();
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newDifferentialMarkupEngine(
      array(
        'differential.diff' => $this->arbitraryDiffForFacebook,
      ));
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return (bool)$this->getPHID();
  }

  public function getDateCreated() {
    return $this->proxy->getDateCreated();
  }

  public function getRevisionPHID() {
    return $this->proxy->getObjectPHID();
  }

  public function save() {
    $this->proxy->openTransaction();
      $this->proxy
        ->setViewPolicy('public')
        ->setEditPolicy($this->getAuthorPHID())
        ->save();

      if ($this->getContent() !== null ||
          $this->getProxyComment()->getChangesetID()) {

        $this->getProxyComment()
          ->setAuthorPHID($this->getAuthorPHID())
          ->setViewPolicy('public')
          ->setEditPolicy($this->getAuthorPHID())
          ->setCommentVersion(1)
          ->setTransactionPHID($this->proxy->getPHID())
          ->save();

        $this->proxy
          ->setCommentVersion(1)
          ->setCommentPHID($this->getProxyComment()->getPHID())
          ->save();
      }

    $this->proxy->saveTransaction();

    return $this;
  }

  public function delete() {
    $this->proxy->delete();
    return $this;
  }

  public function getProxyTransaction() {
    return $this->proxy;
  }

}
