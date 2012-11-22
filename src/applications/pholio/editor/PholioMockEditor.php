<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group pholio
 */
final class PholioMockEditor extends PhabricatorEditor {

  private $contentSource;

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  public function applyTransactions(PholioMock $mock, array $xactions) {
    assert_instances_of($xactions, 'PholioTransaction');

    $actor = $this->requireActor();
    if (!$this->contentSource) {
      throw new Exception(
        "Call setContentSource() before applyTransactions()!");
    }

    $comments = array();
    foreach ($xactions as $xaction) {
      if (strlen($xaction->getComment())) {
        $comments[] = $xaction->getComment();
      }
      $type = $xaction->getTransactionType();
      if ($type == PholioTransactionType::TYPE_DESCRIPTION) {
        $comments[] = $xaction->getNewValue();
      }
    }

    $mentioned_phids = PhabricatorMarkupEngine::extractPHIDsFromMentions(
      $comments);

    if ($mentioned_phids) {
      if ($mock->getID()) {
        $old_subs = PhabricatorSubscribersQuery::loadSubscribersForPHID(
          $mock->getPHID());
      } else {
        $old_subs = array();
      }

      $new_subs = array_merge($old_subs, $mentioned_phids);
      $xaction = id(new PholioTransaction())
        ->setTransactionType(PholioTransactionType::TYPE_SUBSCRIBERS)
        ->setOldValue($old_subs)
        ->setNewValue($new_subs);
      array_unshift($xactions, $xaction);
    }

    foreach ($xactions as $xaction) {
      $xaction->setContentSource($this->contentSource);
      $xaction->setAuthorPHID($actor->getPHID());
    }

    foreach ($xactions as $key => $xaction) {
      $has_effect = $this->applyTransaction($mock, $xaction);
      if (!$has_effect) {
        unset($xactions[$key]);
      }
    }

    if (!$xactions) {
      return;
    }

    $mock->openTransaction();
      $mock->save();

      foreach ($xactions as $xaction) {
        $xaction->setMockID($mock->getID());
        $xaction->save();
      }

      // Apply ID/PHID-dependent transactions.
      foreach ($xactions as $xaction) {
        $type = $xaction->getTransactionType();
        switch ($type) {
          case PholioTransactionType::TYPE_SUBSCRIBERS:
            $subeditor = id(new PhabricatorSubscriptionsEditor())
              ->setObject($mock)
              ->setActor($this->requireActor())
              ->subscribeExplicit($xaction->getNewValue())
              ->save();
            break;
        }
      }

    $mock->saveTransaction();

    PholioIndexer::indexMock($mock);

    return $this;
  }

  private function applyTransaction(
    PholioMock $mock,
    PholioTransaction $xaction) {

    $type = $xaction->getTransactionType();

    $old = null;
    switch ($type) {
      case PholioTransactionType::TYPE_NONE:
        $old = null;
        break;
      case PholioTransactionType::TYPE_NAME:
        $old = $mock->getName();
        break;
      case PholioTransactionType::TYPE_DESCRIPTION:
        $old = $mock->getDescription();
        break;
      case PholioTransactionType::TYPE_VIEW_POLICY:
        $old = $mock->getViewPolicy();
        break;
      case PholioTransactionType::TYPE_SUBSCRIBERS:
        $old = PhabricatorSubscribersQuery::loadSubscribersForPHID(
          $mock->getPHID());
        break;
      default:
        throw new Exception("Unknown transaction type '{$type}'!");
    }

    $xaction->setOldValue($old);

    if (!$this->transactionHasEffect($mock, $xaction)) {
      return false;
    }

    switch ($type) {
      case PholioTransactionType::TYPE_NONE:
        break;
      case PholioTransactionType::TYPE_NAME:
        $mock->setName($xaction->getNewValue());
        break;
      case PholioTransactionType::TYPE_DESCRIPTION:
        $mock->setDescription($xaction->getNewValue());
        break;
      case PholioTransactionType::TYPE_VIEW_POLICY:
        $mock->setViewPolicy($xaction->getNewValue());
        break;
      case PholioTransactionType::TYPE_SUBSCRIBERS:
        // This applies later.
        break;
      default:
        throw new Exception("Unknown transaction type '{$type}'!");
    }

    return true;
  }

  private function transactionHasEffect(
    PholioMock $mock,
    PholioTransaction $xaction) {

    $effect = false;

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PholioTransactionType::TYPE_NONE:
      case PholioTransactionType::TYPE_NAME:
      case PholioTransactionType::TYPE_DESCRIPTION:
      case PholioTransactionType::TYPE_VIEW_POLICY:
        $effect = ($old !== $new);
        break;
      case PholioTransactionType::TYPE_SUBSCRIBERS:
        $old = nonempty($old, array());
        $old_map = array_fill_keys($old, true);
        $filtered = $old;

        foreach ($new as $phid) {
          if ($mock->getAuthorPHID() == $phid) {
            // The author may not be explicitly subscribed.
            continue;
          }
          if (isset($old_map[$phid])) {
            // This PHID was already subscribed.
            continue;
          }
          $filtered[] = $phid;
        }

        $old = array_keys($old_map);
        $new = array_values($filtered);

        $xaction->setOldValue($old);
        $xaction->setNewValue($new);

        $effect = ($old !== $new);
        break;
      default:
        throw new Exception("Unknown transaction type '{$type}'!");
    }

    if (!$effect) {
      if (strlen($xaction->getComment())) {
        $xaction->setTransactionType(PholioTransactionType::TYPE_NONE);
        $effect = true;
      }
    }

    return $effect;
  }

}
