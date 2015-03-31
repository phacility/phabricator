<?php

final class DifferentialReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof DifferentialRevision)) {
      throw new Exception('Receiver is not a DifferentialRevision!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'D');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('D');
  }

  public function getSupportedCommands() {
    $actions = array(
      DifferentialAction::ACTION_COMMENT,
      DifferentialAction::ACTION_REJECT,
      DifferentialAction::ACTION_ABANDON,
      DifferentialAction::ACTION_RECLAIM,
      DifferentialAction::ACTION_RESIGN,
      DifferentialAction::ACTION_RETHINK,
      'unsubscribe',
    );

    if (PhabricatorEnv::getEnvConfig('differential.enable-email-accept')) {
      $actions[] = DifferentialAction::ACTION_ACCEPT;
    }

    return $actions;
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $revision = $this->getMailReceiver();
    $viewer = $this->getActor();

    $body_data = $mail->parseBody();
    $body = $body_data['body'];
    $body = $this->enhanceBodyWithAttachments($body, $mail->getAttachments());

    $xactions = array();
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));

    $template = id(new DifferentialTransaction());

    $commands = $body_data['commands'];
    foreach ($commands as $command_argv) {
      $command = head($command_argv);
      switch ($command) {
        case 'unsubscribe':
          $xactions[] = id(clone $template)
            ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
            ->setNewValue(
              array(
                '-' => array($viewer->getPHID()),
              ));
          break;
        case DifferentialAction::ACTION_ACCEPT:
          $accept_key = 'differential.enable-email-accept';
          $can_accept = PhabricatorEnv::getEnvConfig($accept_key);
          if (!$can_accept) {
            throw new Exception(
              pht(
                'You can not !accept revisions over email because '.
                'Differential is configured to disallow this.'));
          }
          // Fall through...
        case DifferentialAction::ACTION_REJECT:
        case DifferentialAction::ACTION_ABANDON:
        case DifferentialAction::ACTION_RECLAIM:
        case DifferentialAction::ACTION_RESIGN:
        case DifferentialAction::ACTION_RETHINK:
        case DifferentialAction::ACTION_CLAIM:
        case DifferentialAction::ACTION_REOPEN:
          $xactions[] = id(clone $template)
            ->setTransactionType(DifferentialTransaction::TYPE_ACTION)
            ->setNewValue($command);
          break;
      }
    }

    $xactions[] = id(clone $template)
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new DifferentialTransactionComment())
          ->setContent($body));

    $editor = id(new DifferentialTransactionEditor())
      ->setActor($viewer)
      ->setContentSource($content_source)
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true);

    $editor->applyTransactions($revision, $xactions);
  }

}
