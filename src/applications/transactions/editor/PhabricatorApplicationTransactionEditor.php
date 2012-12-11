<?php

abstract class PhabricatorApplicationTransactionEditor
  extends PhabricatorEditor {

  private $contentSource;
  private $object;
  private $xactions;

  private $isNewObject;
  private $mentionedPHIDs;

  protected function getIsNewObject() {
    return $this->isNewObject;
  }

  protected function getMentionedPHIDs() {
    return $this->mentionedPHIDs;
  }

  public function getTransactionTypes() {
    $types = array(
      PhabricatorTransactions::TYPE_COMMENT,
    );

    if ($this->object instanceof PhabricatorSubscribableInterface) {
      $types[] = PhabricatorTransactions::TYPE_SUBSCRIBERS;
    }

    return $types;
  }

  private function adjustTransactionValues(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    $old = $this->getTransactionOldValue($object, $xaction);
    $xaction->setOldValue($old);

    $new = $this->getTransactionNewValue($object, $xaction);
    $xaction->setNewValue($new);
  }

  private function getTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        if ($object->getPHID()) {
          $old_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
            $object->getPHID());
        } else {
          $old_phids = array();
        }
        return array_values($old_phids);
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return $object->getViewPolicy();
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return $object->getEditPolicy();
      default:
        return $this->getCustomTransactionOldValue($object, $xaction);
    }
  }

  private function getTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return $this->getPHIDTransactionNewValue($xaction);
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return $xaction->getNewValue();
      default:
        return $this->getCustomTransactionNewValue($object, $xaction);
    }
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    throw new Exception("Capability not supported!");
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    throw new Exception("Capability not supported!");
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return ($xaction->getOldValue() !== $xaction->getNewValue());
  }

  private function applyInternalEffects(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        break;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $object->setViewPolicy($xaction->getNewValue());
        break;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $object->setEditPolicy($xaction->getNewValue());
        break;
      default:
        return $this->applyCustomInternalTransaction($object, $xaction);
    }
  }

  private function applyExternalEffects(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        break;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $subeditor = id(new PhabricatorSubscriptionsEditor())
          ->setObject($object)
          ->setActor($this->requireActor())
          ->subscribeExplicit($xaction->getNewValue())
          ->save();
        break;
      default:
        return $this->applyCustomExternalTransaction($object, $xaction);
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    throw new Exception("Capability not supported!");
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    throw new Exception("Capability not supported!");
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  protected function didApplyTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return;
  }

  final public function applyTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $this->object = $object;
    $this->xactions = $xactions;
    $this->isNewObject = ($object->getPHID() === null);

    $this->validateEditParameters($object, $xactions);


    $actor = $this->requireActor();

    $mention_xaction = $this->buildMentionTransaction($object, $xactions);
    if ($mention_xaction) {
      $xactions[] = $mention_xaction;
    }

    $xactions = $this->combineTransactions($xactions);

    foreach ($xactions as $xaction) {
      // TODO: This needs to be more sophisticated once we have meta-policies.
      $xaction->setViewPolicy(PhabricatorPolicies::POLICY_PUBLIC);
      $xaction->setEditPolicy($actor->getPHID());

      $xaction->setAuthorPHID($actor->getPHID());
      $xaction->setContentSource($this->getContentSource());
    }

    foreach ($xactions as $xaction) {
      $this->adjustTransactionValues($object, $xaction);
    }

    foreach ($xactions as $key => $xaction) {
      if (!$this->transactionHasEffect($object, $xaction)) {
        // TODO: Raise these to the user.
        if ($xaction->getComment()) {
          $xaction->setTransactionType(
            PhabricatorTransactions::TYPE_COMMENT);
          $xaction->setOldValue(null);
          $xaction->setNewValue(null);
        } else {
          unset($xactions[$key]);
        }
      }
    }

    $xactions = $this->sortTransactions($xactions);

    $comment_editor = id(new PhabricatorApplicationTransactionCommentEditor())
      ->setActor($actor)
      ->setContentSource($this->getContentSource());

    $object->openTransaction();
      foreach ($xactions as $xaction) {
        $this->applyInternalEffects($object, $xaction);
      }

      $object->save();

      foreach ($xactions as $xaction) {
        $xaction->setObjectPHID($object->getPHID());
        if ($xaction->getComment()) {
          $xaction->setPHID($xaction->generatePHID());
          $comment_editor->applyEdit($xaction, $xaction->getComment());
        } else {
          $xaction->save();
        }
      }

      foreach ($xactions as $xaction) {
        $this->applyExternalEffects($object, $xaction);
      }
    $object->saveTransaction();

    // TODO: Send mail.
    // TODO: Index object.
    // TODO: Publish feed/notifications.

    $this->didApplyTransactions($object, $xactions);

    return $this;
  }

  private function validateEditParameters(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if (!$this->getContentSource()) {
      throw new Exception(
        "Call setContentSource() before applyTransactions()!");
    }

    // Do a bunch of sanity checks that the incoming transactions are fresh.
    // They should be unsaved and have only "transactionType" and "newValue"
    // set.

    $types = array_fill_keys($this->getTransactionTypes(), true);

    assert_instances_of($xactions, 'PhabricatorApplicationTransaction');
    foreach ($xactions as $xaction) {
      if ($xaction->getPHID() || $xaction->getID()) {
        throw new Exception(
          "You can not apply transactions which already have IDs/PHIDs!");
      }
      if ($xaction->getObjectPHID()) {
        throw new Exception(
          "You can not apply transactions which already have objectPHIDs!");
      }
      if ($xaction->getAuthorPHID()) {
        throw new Exception(
          "You can not apply transactions which already have authorPHIDs!");
      }
      if ($xaction->getCommentPHID()) {
        throw new Exception(
          "You can not apply transactions which already have commentPHIDs!");
      }
      if ($xaction->getCommentVersion() !== 0) {
        throw new Exception(
          "You can not apply transactions which already have commentVersions!");
      }
      if ($xaction->getOldValue() !== null) {
        throw new Exception(
          "You can not apply transactions which already have oldValue!");
      }

      $type = $xaction->getTransactionType();
      if (empty($types[$type])) {
        throw new Exception("Transaction has unknown type '{$type}'.");
      }
    }

    // The actor must have permission to view and edit the object.

    $actor = $this->requireActor();

    PhabricatorPolicyFilter::requireCapability(
      $actor,
      $xaction,
      PhabricatorPolicyCapability::CAN_VIEW);

    PhabricatorPolicyFilter::requireCapability(
      $actor,
      $xaction,
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  private function buildMentionTransaction(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      return null;
    }

    $texts = array();
    foreach ($xactions as $xaction) {
      $texts[] = $this->getMentionableTextsFromTransaction($xaction);
    }
    $texts = array_mergev($texts);

    $phids = PhabricatorMarkupEngine::extractPHIDsFromMentions($texts);

    $this->mentionedPHIDs = $phids;

    if (!$phids) {
      return null;
    }

    $xaction = newv(get_class(head($xactions)), array());
    $xaction->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS);
    $xaction->setNewValue(array('+' => $phids));

    return $xaction;
  }

  protected function getMentionableTextsFromTransaction(
    PhabricatorApplicationTransaction $transaction) {
    $texts = array();
    if ($transaction->getComment()) {
      $texts[] = $transaction->getComment()->getContent();
    }
    return $texts;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();

    switch ($type) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return $this->mergePHIDTransactions($u, $v);
    }

    // By default, do not merge the transactions.
    return null;
  }


  /**
   * Attempt to combine similar transactions into a smaller number of total
   * transactions. For example, two transactions which edit the title of an
   * object can be merged into a single edit.
   */
  private function combineTransactions(array $xactions) {
    $stray_comments = array();

    $result = array();
    $types = array();
    foreach ($xactions as $key => $xaction) {
      $type = $xaction->getTransactionType();
      if (isset($types[$type])) {
        foreach ($types[$type] as $other_key) {
          $merged = $this->mergeTransactions($result[$other_key], $xaction);
          if ($merged) {
            $result[$other_key] = $merged;

            if ($xaction->getComment() &&
                ($xaction->getComment() !== $merged->getComment())) {
              $stray_comments[] = $xaction->getComment();
            }

            if ($result[$other_key]->getComment() &&
                ($result[$other_key]->getComment() !== $merged->getComment())) {
              $stray_comments[] = $result[$other_key]->getComment();
            }

            // Move on to the next transaction.
            continue 2;
          }
        }
      }
      $result[$key] = $xaction;
      $types[$type][] = $key;
    }

    // If we merged any comments away, restore them.
    foreach ($stray_comments as $comment) {
      $xaction = newv(get_class(head($result)), array());
      $xaction->setTransactionType(PhabricatorTransactions::TYPE_COMMENT);
      $xaction->setComment($comment);
      $result[] = $xaction;
    }

    return array_values($result);
  }

  protected function mergePHIDTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $result = $u->getNewValue();
    foreach ($v->getNewValue() as $key => $value) {
      $result[$key] = array_merge($value, idx($result, $key, array()));
    }
    $u->setNewValue($result);

    return $u;
  }


  protected function getPHIDTransactionNewValue(
    PhabricatorApplicationTransaction $xaction) {

    $old = array_combine($xaction->getOldValue(), $xaction->getOldValue());

    $new = $xaction->getNewValue();
    $new_add = idx($new, '+', array());
    unset($new['+']);
    $new_rem = idx($new, '-', array());
    unset($new['-']);
    $new_set = idx($new, '=', null);
    if ($new_set !== null) {
      $new_set = array_combine($new_set, $new_set);
    }
    unset($new['=']);

    if ($new) {
      throw new Exception(
        "Invalid 'new' value for PHID transaction. Value should contain only ".
        "keys '+' (add PHIDs), '-' (remove PHIDs) and '=' (set PHIDS).");
    }

    $result = array();

    foreach ($old as $phid) {
      if ($new_set !== null && empty($new_set[$phid])) {
        continue;
      }
      $result[$phid] = $phid;
    }

    if ($new_set !== null) {
      foreach ($new_set as $phid) {
        $result[$phid] = $phid;
      }
    }

    foreach ($new_add as $phid) {
      $result[$phid] = $phid;
    }

    foreach ($new_rem as $phid) {
      unset($result[$phid]);
    }

    return array_values($result);
  }

  protected function sortTransactions(array $xactions) {
    $head = array();
    $tail = array();

    // Move bare comments to the end, so the actions preceed them.
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type == PhabricatorTransactions::TYPE_COMMENT) {
        $tail[] = $xaction;
      } else {
        $head[] = $xaction;
      }
    }

    return array_values(array_merge($head, $tail));
  }


}
