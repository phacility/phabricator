<?php

final class ManiphestTransaction
  implements PhabricatorMarkupInterface {

  const MARKUP_FIELD_BODY = 'markup:body';

  private $proxy;
  private $pendingComment;

  public function __construct() {
    $this->proxy = new ManiphestTransactionPro();
  }

  public function __clone() {
    $this->proxy = clone $this->proxy;
  }

  public static function newFromModernTransaction(
    ManiphestTransactionPro $pro) {

    $obj = new ManiphestTransaction();
    $obj->proxy = $pro;

    return $obj;
  }

  public function getModernTransaction() {
    return $this->proxy;
  }

  public function save() {
    $this->proxy->openTransaction();
      $this->proxy
        ->setViewPolicy('public')
        ->setEditPolicy($this->getAuthorPHID())
        ->save();
      if ($this->pendingComment) {
        $comment = id(new ManiphestTransactionComment())
          ->setTransactionPHID($this->proxy->getPHID())
          ->setCommentVersion(1)
          ->setAuthorPHID($this->getAuthorPHID())
          ->setViewPolicy('public')
          ->setEditPolicy($this->getAuthorPHID())
          ->setContent($this->pendingComment)
          ->setContentSource($this->getContentSource())
          ->setIsDeleted(0)
          ->save();

        $this->proxy
          ->setCommentVersion(1)
          ->setCommentPHID($comment->getPHID())
          ->save();

        $this->pendingComment = null;
      }
    $this->proxy->saveTransaction();

    return $this;
  }

  public function setTransactionTask(ManiphestTask $task) {
    $this->proxy->setObjectPHID($task->getPHID());
    return $this;
  }

  public function getTaskPHID() {
    return $this->proxy->getObjectPHID();
  }

  public function getID() {
    return $this->proxy->getID();
  }

  public function setTaskID() {
    throw new Exception("No longer supported!");
  }

  public function getTaskID() {
    throw new Exception("No longer supported!");
  }

  public function getAuthorPHID() {
    return $this->proxy->getAuthorPHID();
  }

  public function setAuthorPHID($phid) {
    $this->proxy->setAuthorPHID($phid);
    return $this;
  }

  public function getOldValue() {
    return $this->proxy->getOldValue();
  }

  public function setOldValue($value) {
    $this->proxy->setOldValue($value);
    return $this;
  }

  public function getNewValue() {
    return $this->proxy->getNewValue();
  }

  public function setNewValue($value) {
    $this->proxy->setNewValue($value);
    return $this;
  }

  public function getTransactionType() {
    return $this->proxy->getTransactionType();
  }

  public function setTransactionType($value) {
    $this->proxy->setTransactionType($value);
    return $this;
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->proxy->setContentSource($content_source);
    return $this;
  }

  public function getContentSource() {
    return $this->proxy->getContentSource();
  }

  public function getMetadataValue($key, $default = null) {
    return $this->proxy->getMetadataValue($key, $default);
  }

  public function setMetadataValue($key, $value) {
    $this->proxy->setMetadataValue($key, $value);
    return $this;
  }

  public function getComments() {
    if ($this->pendingComment) {
      return $this->pendingComment;
    }
    if ($this->proxy->getComment()) {
      return $this->proxy->getComment()->getContent();
    }
    return null;
  }

  public function setComments($comment) {
    $this->pendingComment = $comment;
    return $this;
  }

  public function getDateCreated() {
    return $this->proxy->getDateCreated();
  }

  public function getDateModified() {
    return $this->proxy->getDateModified();
  }

  public function extractPHIDs() {
    $phids = array();

    switch ($this->getTransactionType()) {
      case ManiphestTransactionType::TYPE_CCS:
      case ManiphestTransactionType::TYPE_PROJECTS:
        foreach ($this->getOldValue() as $phid) {
          $phids[] = $phid;
        }
        foreach ($this->getNewValue() as $phid) {
          $phids[] = $phid;
        }
        break;
      case ManiphestTransactionType::TYPE_OWNER:
        $phids[] = $this->getOldValue();
        $phids[] = $this->getNewValue();
        break;
      case ManiphestTransactionType::TYPE_EDGE:
        $phids = array_merge(
          $phids,
          array_keys($this->getOldValue() + $this->getNewValue()));
        break;
      case ManiphestTransactionType::TYPE_ATTACH:
        $old = $this->getOldValue();
        $new = $this->getNewValue();
        if (!is_array($old)) {
          $old = array();
        }
        if (!is_array($new)) {
          $new = array();
        }
        $val = array_merge(array_values($old), array_values($new));
        foreach ($val as $stuff) {
          foreach ($stuff as $phid => $ignored) {
            $phids[] = $phid;
          }
        }
        break;
    }

    $phids[] = $this->getAuthorPHID();

    return $phids;
  }

  public function canGroupWith($target) {
    if ($target->getAuthorPHID() != $this->getAuthorPHID()) {
      return false;
    }
    if ($target->hasComments() && $this->hasComments()) {
      return false;
    }
    $ttime = $target->getDateCreated();
    $stime = $this->getDateCreated();
    if (abs($stime - $ttime) > 60) {
      return false;
    }

    if ($target->getTransactionType() == $this->getTransactionType()) {
      $aux_type = PhabricatorTransactions::TYPE_CUSTOMFIELD;
      if ($this->getTransactionType() == $aux_type) {
        $that_key = $target->getMetadataValue('customfield:key');
        $this_key = $this->getMetadataValue('customfield:key');
        if ($that_key == $this_key) {
          return false;
        }
      } else {
        return false;
      }
    }

    return true;
  }

  public function hasComments() {
    return (bool)strlen(trim($this->getComments()));
  }


/* -(  Markup Interface  )--------------------------------------------------- */


  /**
   * @task markup
   */
  public function getMarkupFieldKey($field) {
    if ($this->shouldUseMarkupCache($field)) {
      $id = $this->getID();
    } else {
      $id = PhabricatorHash::digest($this->getMarkupText($field));
    }
    return "maniphest:x:{$field}:{$id}";
  }


  /**
   * @task markup
   */
  public function getMarkupText($field) {
    return $this->getComments();
  }


  /**
   * @task markup
   */
  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newManiphestMarkupEngine();
  }


  /**
   * @task markup
   */
  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }


  /**
   * @task markup
   */
  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }

}
