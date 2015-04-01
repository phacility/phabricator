<?php

final class ConpherenceReplyHandler extends PhabricatorMailReplyHandler {

  private $mailAddedParticipantPHIDs;

  public function setMailAddedParticipantPHIDs(array $phids) {
    $this->mailAddedParticipantPHIDs = $phids;
    return $this;
  }
  public function getMailAddedParticipantPHIDs() {
    return $this->mailAddedParticipantPHIDs;
  }

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof ConpherenceThread)) {
      throw new Exception('Mail receiver is not a ConpherenceThread!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'Z');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('Z');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $conpherence = $this->getMailReceiver();
    $user = $this->getActor();
    if (!$conpherence->getPHID()) {
      $conpherence
        ->attachParticipants(array())
        ->attachFilePHIDs(array());
    } else {
      $edge_type = PhabricatorObjectHasFileEdgeType::EDGECONST;
      $file_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $conpherence->getPHID(),
        $edge_type);
      $conpherence->attachFilePHIDs($file_phids);
      $participants = id(new ConpherenceParticipant())
        ->loadAllWhere('conpherencePHID = %s', $conpherence->getPHID());
      $participants = mpull($participants, null, 'getParticipantPHID');
      $conpherence->attachParticipants($participants);
    }

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));

    $editor = id(new ConpherenceEditor())
      ->setActor($user)
      ->setContentSource($content_source)
      ->setParentMessageID($mail->getMessageID());

    $body = $mail->getCleanTextBody();
    $body = $this->enhanceBodyWithAttachments($body, $mail->getAttachments());

    $xactions = array();
    if ($this->getMailAddedParticipantPHIDs()) {
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(ConpherenceTransactionType::TYPE_PARTICIPANTS)
        ->setNewValue(array('+' => $this->getMailAddedParticipantPHIDs()));
    }

    $xactions = array_merge(
      $xactions,
      $editor->generateTransactionsFromText(
        $user,
        $conpherence,
        $body));

    $editor->applyTransactions($conpherence, $xactions);

    return $conpherence;
  }

}
