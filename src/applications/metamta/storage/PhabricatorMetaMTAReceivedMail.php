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

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'headers'     => self::SERIALIZATION_JSON,
        'bodies'      => self::SERIALIZATION_JSON,
        'attachments' => self::SERIALIZATION_JSON,
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

      $receiver->receiveMail($this, $sender);
    } catch (PhabricatorMetaMTAReceivedMailProcessingException $ex) {
      $this
        ->setStatus($ex->getStatusCode())
        ->setMessage($ex->getMessage())
        ->save();
      return $this;
    } catch (Exception $ex) {
      $this
        ->setStatus(MetaMTAReceivedMailStatus::STATUS_UNHANDLED_EXCEPTION)
        ->setMessage(pht('Unhandled Exception: %s', $ex->getMessage()))
        ->save();

      throw $ex;
    }

    return $this->setMessage('OK')->save();
  }

  public function getCleanTextBody() {
    $body = idx($this->bodies, 'text');

    $parser = new PhabricatorMetaMTAEmailBodyParser();
    return $parser->stripTextBody($body);
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
      "Ignoring email with 'X-Phabricator-Sent-This-Message' header to avoid ".
      "loops.");
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

    $message = sprintf(
      'Ignoring email with message id hash "%s" that has been seen %d '.
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
        "No concrete, enabled subclasses of `PhabricatorMailReceiver` can ".
        "accept this mail.");
    }

    if (count($accept) > 1) {
      $names = implode(', ', array_keys($accept));
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_ABUNDANT_RECEIVERS,
        "More than one `PhabricatorMailReceiver` claims to accept this mail.");
    }

    return head($accept);
  }

}
