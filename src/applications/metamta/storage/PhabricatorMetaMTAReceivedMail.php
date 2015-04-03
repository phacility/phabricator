<?php

final class PhabricatorMetaMTAReceivedMail extends PhabricatorMetaMTADAO {

  protected $headers = array();
  protected $bodies = array();
  protected $attachments = array();
  protected $status = '';

  protected $relatedPHID;
  protected $authorPHID;
  protected $message;
  protected $messageIDHash = '';

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'headers'     => self::SERIALIZATION_JSON,
        'bodies'      => self::SERIALIZATION_JSON,
        'attachments' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'relatedPHID' => 'phid?',
        'authorPHID' => 'phid?',
        'message' => 'text?',
        'messageIDHash' => 'bytes12',
        'status' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'relatedPHID' => array(
          'columns' => array('relatedPHID'),
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID'),
        ),
        'key_messageIDHash' => array(
          'columns' => array('messageIDHash'),
        ),
        'key_created' => array(
          'columns' => array('dateCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setHeaders(array $headers) {
    // Normalize headers to lowercase.
    $normalized = array();
    foreach ($headers as $name => $value) {
      $name = $this->normalizeMailHeaderName($name);
      if ($name == 'message-id') {
        $this->setMessageIDHash(PhabricatorHash::digestForIndex($value));
      }
      $normalized[$name] = $value;
    }
    $this->headers = $normalized;
    return $this;
  }

  public function getHeader($key, $default = null) {
    $key = $this->normalizeMailHeaderName($key);
    return idx($this->headers, $key, $default);
  }

  private function normalizeMailHeaderName($name) {
    return strtolower($name);
  }

  public function getMessageID() {
    return $this->getHeader('Message-ID');
  }

  public function getSubject() {
    return $this->getHeader('Subject');
  }

  public function getCCAddresses() {
    return $this->getRawEmailAddresses(idx($this->headers, 'cc'));
  }

  public function getToAddresses() {
    return $this->getRawEmailAddresses(idx($this->headers, 'to'));
  }

  public function loadExcludeMailRecipientPHIDs() {
    $addresses = array_merge(
      $this->getToAddresses(),
      $this->getCCAddresses());

    return $this->loadPHIDsFromAddresses($addresses);
  }

  final public function loadCCPHIDs() {
    return $this->loadPHIDsFromAddresses($this->getCCAddresses());
  }

  private function loadPHIDsFromAddresses(array $addresses) {
    if (empty($addresses)) {
      return array();
    }
    $users = id(new PhabricatorUserEmail())
      ->loadAllWhere('address IN (%Ls)', $addresses);
    $user_phids = mpull($users, 'getUserPHID');

    $mailing_lists = id(new PhabricatorMetaMTAMailingList())
      ->loadAllWhere('email in (%Ls)', $addresses);
    $mailing_list_phids = mpull($mailing_lists, 'getPHID');

    return array_merge($user_phids,  $mailing_list_phids);
  }

  public function processReceivedMail() {

    try {
      $this->dropMailFromPhabricator();
      $this->dropMailAlreadyReceived();

      $receiver = $this->loadReceiver();
      $sender = $receiver->loadSender($this);
      $receiver->validateSender($this, $sender);

      $this->setAuthorPHID($sender->getPHID());

      // Now that we've identified the sender, mark them as the author of
      // any attached files.
      $attachments = $this->getAttachments();
      if ($attachments) {
        $files = id(new PhabricatorFileQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPHIDs($attachments)
          ->execute();
        foreach ($files as $file) {
          $file->setAuthorPHID($sender->getPHID())->save();
        }
      }

      $receiver->receiveMail($this, $sender);
    } catch (PhabricatorMetaMTAReceivedMailProcessingException $ex) {
      switch ($ex->getStatusCode()) {
        case MetaMTAReceivedMailStatus::STATUS_DUPLICATE:
        case MetaMTAReceivedMailStatus::STATUS_FROM_PHABRICATOR:
          // Don't send an error email back in these cases, since they're
          // very unlikely to be the sender's fault.
          break;
        case MetaMTAReceivedMailStatus::STATUS_EMPTY_IGNORED:
          // This error is explicitly ignored.
          break;
        default:
          $this->sendExceptionMail($ex);
          break;
      }

      $this
        ->setStatus($ex->getStatusCode())
        ->setMessage($ex->getMessage())
        ->save();
      return $this;
    } catch (Exception $ex) {
      $this->sendExceptionMail($ex);

      $this
        ->setStatus(MetaMTAReceivedMailStatus::STATUS_UNHANDLED_EXCEPTION)
        ->setMessage(pht('Unhandled Exception: %s', $ex->getMessage()))
        ->save();

      throw $ex;
    }

    return $this->setMessage('OK')->save();
  }

  public function getCleanTextBody() {
    $body = $this->getRawTextBody();
    $parser = new PhabricatorMetaMTAEmailBodyParser();
    return $parser->stripTextBody($body);
  }

  public function parseBody() {
    $body = $this->getRawTextBody();
    $parser = new PhabricatorMetaMTAEmailBodyParser();
    return $parser->parseBody($body);
  }

  public function getRawTextBody() {
    return idx($this->bodies, 'text');
  }

  /**
   * Strip an email address down to the actual user@domain.tld part if
   * necessary, since sometimes it will have formatting like
   * '"Abraham Lincoln" <alincoln@logcab.in>'.
   */
  private function getRawEmailAddress($address) {
    $matches = null;
    $ok = preg_match('/<(.*)>/', $address, $matches);
    if ($ok) {
      $address = $matches[1];
    }
    return $address;
  }

  private function getRawEmailAddresses($addresses) {
    $raw_addresses = array();
    foreach (explode(',', $addresses) as $address) {
      $raw_addresses[] = $this->getRawEmailAddress($address);
    }
    return array_filter($raw_addresses);
  }

  /**
   * If Phabricator sent the mail, always drop it immediately. This prevents
   * loops where, e.g., the public bug address is also a user email address
   * and creating a bug sends them an email, which loops.
   */
  private function dropMailFromPhabricator() {
    if (!$this->getHeader('x-phabricator-sent-this-message')) {
      return;
    }

    throw new PhabricatorMetaMTAReceivedMailProcessingException(
      MetaMTAReceivedMailStatus::STATUS_FROM_PHABRICATOR,
      pht(
        "Ignoring email with 'X-Phabricator-Sent-This-Message' header to ".
        "avoid loops."));
  }

  /**
   * If this mail has the same message ID as some other mail, and isn't the
   * first mail we we received with that message ID, we drop it as a duplicate.
   */
  private function dropMailAlreadyReceived() {
    $message_id_hash = $this->getMessageIDHash();
    if (!$message_id_hash) {
      // No message ID hash, so we can't detect duplicates. This should only
      // happen with very old messages.
      return;
    }

    $messages = $this->loadAllWhere(
      'messageIDHash = %s ORDER BY id ASC LIMIT 2',
      $message_id_hash);
    $messages_count = count($messages);
    if ($messages_count <= 1) {
      // If we only have one copy of this message, we're good to process it.
      return;
    }

    $first_message = reset($messages);
    if ($first_message->getID() == $this->getID()) {
      // If this is the first copy of the message, it is okay to process it.
      // We may not have been able to to process it immediately when we received
      // it, and could may have received several copies without processing any
      // yet.
      return;
    }

    $message = pht(
      'Ignoring email with "Message-ID" hash "%s" that has been seen %d '.
      'times, including this message.',
      $message_id_hash,
      $messages_count);

    throw new PhabricatorMetaMTAReceivedMailProcessingException(
      MetaMTAReceivedMailStatus::STATUS_DUPLICATE,
      $message);
  }


  /**
   * Load a concrete instance of the @{class:PhabricatorMailReceiver} which
   * accepts this mail, if one exists.
   */
  private function loadReceiver() {
    $receivers = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorMailReceiver')
      ->loadObjects();

    $accept = array();
    foreach ($receivers as $key => $receiver) {
      if (!$receiver->isEnabled()) {
        continue;
      }
      if ($receiver->canAcceptMail($this)) {
        $accept[$key] = $receiver;
      }
    }

    if (!$accept) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_NO_RECEIVERS,
        pht(
          'Phabricator can not process this mail because no application '.
          'knows how to handle it. Check that the address you sent it to is '.
          'correct.'.
          "\n\n".
          '(No concrete, enabled subclass of PhabricatorMailReceiver can '.
          'accept this mail.)'));
    }

    if (count($accept) > 1) {
      $names = implode(', ', array_keys($accept));
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_ABUNDANT_RECEIVERS,
        pht(
          'Phabricator is not able to process this mail because more than '.
          'one application is willing to accept it, creating ambiguity. '.
          'Mail needs to be accepted by exactly one receiving application.'.
          "\n\n".
          'Accepting receivers: %s.',
          $names));
    }

    return head($accept);
  }

  private function sendExceptionMail(Exception $ex) {
    $from = $this->getHeader('from');
    if (!strlen($from)) {
      return;
    }

    if ($ex instanceof PhabricatorMetaMTAReceivedMailProcessingException) {
      $status_code = $ex->getStatusCode();
      $status_name = MetaMTAReceivedMailStatus::getHumanReadableName(
        $status_code);

      $title = pht('Error Processing Mail (%s)', $status_name);
      $description = $ex->getMessage();
    } else {
      $title = pht('Error Processing Mail (%s)', get_class($ex));
      $description = pht('%s: %s', get_class($ex), $ex->getMessage());
    }

    // TODO: Since headers don't necessarily have unique names, this may not
    // really be all the headers. It would be nice to pass the raw headers
    // through from the upper layers where possible.

    $headers = array();
    foreach ($this->headers as $key => $value) {
      $headers[] = pht('%s: %s', $key, $value);
    }
    $headers = implode("\n", $headers);

    $body = pht(<<<EOBODY
Your email to Phabricator was not processed, because an error occurred while
trying to handle it:

%s

-- Original Message Body -----------------------------------------------------

%s

-- Original Message Headers --------------------------------------------------

%s

EOBODY
,
      wordwrap($description, 78),
      $this->getRawTextBody(),
      $headers);

    $mail = id(new PhabricatorMetaMTAMail())
      ->setIsErrorEmail(true)
      ->setForceDelivery(true)
      ->setSubject($title)
      ->addRawTos(array($from))
      ->setBody($body)
      ->saveAndSend();
  }

}
