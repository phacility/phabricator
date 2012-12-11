<?php

/**
 * @group pholio
 */
final class PholioMockEditor extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    $types[] = PholioTransactionType::TYPE_NAME;
    $types[] = PholioTransactionType::TYPE_DESCRIPTION;
    return $types;
  }

  protected function didApplyTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {
//    $this->sendMail($object, $xactions);
//    PholioIndexer::indexMock($mock);
    return;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PholioTransactionType::TYPE_NAME:
        return $object->getName();
      case PholioTransactionType::TYPE_DESCRIPTION:
        return $object->getDescription();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PholioTransactionType::TYPE_NAME:
      case PholioTransactionType::TYPE_DESCRIPTION:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PholioTransactionType::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        if ($object->getOriginalName() === null) {
          $object->setOriginalName($xaction->getNewValue());
        }
        break;
      case PholioTransactionType::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case PholioTransactionType::TYPE_NAME:
      case PholioTransactionType::TYPE_DESCRIPTION:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
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


}
