<?php

final class PhabricatorAuditEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;

    // TODO: These will get modernized eventually, but that can happen one
    // at a time later on.
    $types[] = PhabricatorAuditActionConstants::ACTION;
    $types[] = PhabricatorAuditActionConstants::INLINE;
    $types[] = PhabricatorAuditActionConstants::ADD_AUDITORS;

    return $types;
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::INLINE:
        return $xaction->hasComment();
    }

    return parent::transactionHasEffect($object, $xaction);
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
        return null;
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        // TODO: For now, just record the added PHIDs. Eventually, turn these
        // into real edge transactions, probably?
        return array();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
        return;
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        $new = $xaction->getNewValue();
        if (!is_array($new)) {
          $new = array();
        }

        $old = $xaction->getOldValue();
        if (!is_array($old)) {
          $old = array();
        }

        $add = array_diff_key($new, $old);

        $actor = $this->requireActor();

        $requests = $object->getAudits();
        $requests = mpull($requests, null, 'getAuditorPHID');
        foreach ($add as $phid) {
          if (isset($requests[$phid])) {
            continue;
          }

          $audit_requested = PhabricatorAuditStatusConstants::AUDIT_REQUESTED;
          $requests[] = id (new PhabricatorRepositoryAuditRequest())
            ->setCommitPHID($object->getPHID())
            ->setAuditorPHID($phid)
            ->setAuditStatus($audit_requested)
            ->setAuditReasons(
              array(
                'Added by '.$actor->getUsername(),
              ))
            ->save();
        }

        $object->updateAuditStatus($requests);
        $object->attachAudits($requests);
        $object->save();
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function sortTransactions(array $xactions) {
    $xactions = parent::sortTransactions($xactions);

    $head = array();
    $tail = array();

    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type == PhabricatorAuditActionConstants::INLINE) {
        $tail[] = $xaction;
      } else {
        $head[] = $xaction;
      }
    }

    return array_values(array_merge($head, $tail));
  }

  protected function supportsSearch() {
    return true;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    $reply_handler = PhabricatorEnv::newObjectFromConfig(
      'metamta.diffusion.reply-handler');
    $reply_handler->setMailReceiver($object);
    return $reply_handler;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.diffusion.subject-prefix');
  }

  protected function getMailThreadID(PhabricatorLiskDAO $object) {
    // For backward compatibility, use this legacy thread ID.
    return 'diffusion-audit-'.$object->getPHID();
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $identifier = $object->getCommitIdentifier();
    $repository = $object->getRepository();
    $monogram = $repository->getMonogram();

    $summary = $object->getSummary();
    $name = $repository->formatCommitName($identifier);

    $subject = "{$name}: {$summary}";
    $thread_topic = "Commit {$monogram}{$identifier}";

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($subject)
      ->addHeader('Thread-Topic', $thread_topic);
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();
    if ($object->getAuthorPHID()) {
      $phids[] = $object->getAuthorPHID();
    }

    $status_resigned = PhabricatorAuditStatusConstants::RESIGNED;
    foreach ($object->getAudits() as $audit) {
      if ($audit->getAuditStatus() != $status_resigned) {
        $phids[] = $audit->getAuditorPHID();
      }
    }

    return $phids;
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $type_inline = PhabricatorAuditActionConstants::INLINE;

    $inlines = array();
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_inline) {
        $inlines[] = $xaction;
      }
    }

    if ($inlines) {
      $body->addTextSection(
        pht('INLINE COMMENTS'),
        $this->renderInlineCommentsForMail($object, $inlines));
    }

    $monogram = $object->getRepository()->formatCommitName(
      $object->getCommitIdentifier());

    $body->addTextSection(
      pht('COMMIT'),
      PhabricatorEnv::getProductionURI('/'.$monogram));

    return $body;
  }

  private function renderInlineCommentsForMail(
    PhabricatorLiskDAO $object,
    array $inline_xactions) {

    $inlines = mpull($inline_xactions, 'getComment');

    $block = array();

    $path_map = id(new DiffusionPathQuery())
      ->withPathIDs(mpull($inlines, 'getPathID'))
      ->execute();
    $path_map = ipull($path_map, 'path', 'id');

    foreach ($inlines as $inline) {
      $path = idx($path_map, $inline->getPathID());
      if ($path === null) {
        continue;
      }

      $start = $inline->getLineNumber();
      $len   = $inline->getLineLength();
      if ($len) {
        $range = $start.'-'.($start + $len);
      } else {
        $range = $start;
      }

      $content = $inline->getContent();
      $block[] = "{$path}:{$range} {$content}";
    }

    return implode("\n", $block);
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

}
