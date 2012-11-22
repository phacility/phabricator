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
      default:
        throw new Exception("Unknown transaction type '{$type}'!");
    }

    $xaction->setOldValue($old);

    if (!$this->transactionHasEffect($xaction)) {
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
      default:
        throw new Exception("Unknown transaction type '{$type}'!");
    }

    return true;
  }

  private function transactionHasEffect(PholioTransaction $xaction) {
    $effect = false;

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PholioTransactionType::TYPE_NONE:
      case PholioTransactionType::TYPE_NAME:
      case PholioTransactionType::TYPE_DESCRIPTION:
      case PholioTransactionType::TYPE_VIEW_POLICY:
        $effect = ($xaction->getOldValue() !== $xaction->getNewValue());
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
