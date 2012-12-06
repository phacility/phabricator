<?php

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

    $is_new = !$mock->getID();

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
    $subscribe_phids = $mentioned_phids;

    // Attempt to subscribe the actor.
    $subscribe_phids[] = $actor->getPHID();

    if ($subscribe_phids) {
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

    $this->sendMail($mock, $xactions, $is_new, $mentioned_phids);

    PholioIndexer::indexMock($mock);

    return $this;
  }

  private function sendMail(
    PholioMock $mock,
    array $xactions,
    $is_new,
    array $mentioned_phids) {

    $subscribed_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $mock->getPHID());

    $email_to = array(
      $mock->getAuthorPHID(),
      $this->requireActor()->getPHID(),
    );
    $email_cc = $subscribed_phids;

    $phids = array_merge($email_to, $email_cc);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($this->requireActor())
      ->loadHandles();

    $mock_id = $mock->getID();
    $name = $mock->getName();
    $original_name = $mock->getOriginalName();

    $thread_id = 'pholio-mock-'.$mock->getPHID();

    $mail_tags = $this->getMailTags($mock, $xactions);

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection('lorem ipsum');

    $mock_uri = PhabricatorEnv::getProductionURI('/M'.$mock->getID());

    $body->addTextSection(pht('MOCK DETAIL'), $mock_uri);

    $reply_handler = $this->buildReplyHandler($mock);

    $template = id(new PhabricatorMetaMTAMail())
      ->setSubject("M{$mock_id}: {$name}")
      ->setSubjectPrefix($this->getMailSubjectPrefix())
      ->setVarySubjectPrefix('[edit/create?]')
      ->setFrom($this->requireActor()->getPHID())
      ->addHeader('Thread-Topic', "M{$mock_id}: {$original_name}")
      ->setThreadID($thread_id, $is_new)
      ->setRelatedPHID($mock->getPHID())
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setIsBulk(true)
      ->setMailTags($mail_tags)
      ->setBody($body->render());

    // TODO
    //  ->setParentMessageID(...)

    $mails = $reply_handler->multiplexMail(
      $template,
      array_select_keys($handles, $email_to),
      array_select_keys($handles, $email_cc));

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }

    $template->addTos($email_to);
    $template->addCCs($email_cc);

    return $template;
  }

  private function getMailTags(PholioMock $mock, array $xactions) {
    assert_instances_of($xactions, 'PholioTransaction');
    $tags = array();

    return $tags;
  }

  public function buildReplyHandler(PholioMock $mock) {
    $handler_object = new PholioReplyHandler();
    $handler_object->setMailReceiver($mock);

    return $handler_object;
  }

  private function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.pholio.subject-prefix');
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
        if ($mock->getOriginalName() === null) {
          $mock->setOriginalName($xaction->getNewValue());
        }
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
