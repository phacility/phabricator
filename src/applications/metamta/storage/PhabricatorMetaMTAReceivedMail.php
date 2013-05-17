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

  /**
   * Parses "to" addresses, looking for a public create email address
   * first and if not found parsing the "to" address for reply handler
   * information: receiver name, user id, and hash. If nothing can be
   * found, it then loads user phids for as many to: email addresses as
   * it can, theoretically falling back to create a conpherence amongst
   * those users.
   */
  private function getPhabricatorToInformation() {
    // Only one "public" create address so far
    $create_task = PhabricatorEnv::getEnvConfig(
      'metamta.maniphest.public-create-email');

    // For replies, look for an object address with a format like:
    // D291+291+b0a41ca848d66dcc@example.com
    $single_handle_prefix = PhabricatorEnv::getEnvConfig(
      'metamta.single-reply-handler-prefix');

    $prefixPattern = ($single_handle_prefix)
      ? preg_quote($single_handle_prefix, '/') . '\+'
      : '';
    $pattern = "/^{$prefixPattern}((?:D|T|C|E)\d+)\+([\w]+)\+([a-f0-9]{16})@/U";

    $phabricator_address = null;
    $receiver_name       = null;
    $user_id             = null;
    $hash                = null;
    $user_names          = array();
    foreach ($this->getToAddresses() as $address) {
      if ($address == $create_task) {
        $phabricator_address = $address;
        // it's okay to stop here because we just need to map a create
        // address to an application and don't need / won't have more
        // information in these cases.
        break;
      }

      $matches = null;
      $ok = preg_match(
        $pattern,
        $address,
        $matches);

      if ($ok) {
        $phabricator_address = $address;
        $receiver_name       = $matches[1];
        $user_id             = $matches[2];
        $hash                = $matches[3];
        break;
      }

      $parts = explode('@', $address);
      $maybe_name = trim($parts[0]);
      $maybe_domain = trim($parts[1]);
      $mail_domain = PhabricatorEnv::getEnvConfig('metamta.domain');
      if ($mail_domain == $maybe_domain &&
          PhabricatorUser::validateUsername($maybe_name)) {
        $user_names[] = $maybe_name;
      }
    }

    return array(
      $phabricator_address,
      $receiver_name,
      $user_id,
      $hash,
    );
  }


  public function processReceivedMail() {

    try {
      $this->dropMailFromPhabricator();
      $this->dropMailAlreadyReceived();

      $receiver = $this->loadReceiver();
      $sender = $receiver->loadSender($this);
      $receiver->validateSender($this, $sender);

      $this->setAuthorPHID($sender->getPHID());

      // TODO: Once everything can receive mail, nuke this.
      $can_receive = false;
      if ($receiver instanceof ManiphestCreateMailReceiver) {
        $can_receive = true;
      }
      if ($receiver instanceof ConpherenceCreateThreadMailReceiver) {
        $can_receive = true;
      }

      if ($can_receive) {
        $receiver->receiveMail($this, $sender);
        return $this->setMessage('OK')->save();
      }

    } catch (PhabricatorMetaMTAReceivedMailProcessingException $ex) {
      $this
        ->setStatus($ex->getStatusCode())
        ->setMessage($ex->getMessage())
        ->save();
      return $this;
    }

    list($to,
         $receiver_name,
         $user_id,
         $hash) = $this->getPhabricatorToInformation();
    if (!$to) {
      $raw_to = idx($this->headers, 'to');
      return $this->setMessage("Unrecognized 'to' format: {$raw_to}")->save();
    }

    $from = idx($this->headers, 'from');

    $user = $sender;

    $receiver = self::loadReceiverObject($receiver_name);
    if (!$receiver) {
      return $this->setMessage("Invalid object '{$receiver_name}'")->save();
    }

    $this->setRelatedPHID($receiver->getPHID());

    if ($receiver instanceof ManiphestTask) {
      $editor = new ManiphestTransactionEditor();
      $editor->setActor($user);
      $handler = $editor->buildReplyHandler($receiver);
    } else if ($receiver instanceof DifferentialRevision) {
      $handler = DifferentialMail::newReplyHandlerForRevision($receiver);
    } else if ($receiver instanceof PhabricatorRepositoryCommit) {
      $handler = PhabricatorAuditCommentEditor::newReplyHandlerForCommit(
        $receiver);
    } else if ($receiver instanceof ConpherenceThread) {
      $handler = id(new ConpherenceReplyHandler())
        ->setMailReceiver($receiver);
    }

    $handler->setActor($user);
    $handler->setExcludeMailRecipientPHIDs(
      $this->loadExcludeMailRecipientPHIDs());
    $handler->processEmail($this);

    $this->setMessage('OK');

    return $this->save();
  }

  public function getCleanTextBody() {
    $body = idx($this->bodies, 'text');

    $parser = new PhabricatorMetaMTAEmailBodyParser();
    return $parser->stripTextBody($body);
  }

  public function getRawTextBody() {
    return idx($this->bodies, 'text');
  }

  public static function loadReceiverObject($receiver_name) {
    if (!$receiver_name) {
      return null;
    }

    $receiver_type = $receiver_name[0];
    $receiver_id   = substr($receiver_name, 1);

    $class_obj = null;
    switch ($receiver_type) {
      case 'T':
        $class_obj = new ManiphestTask();
        break;
      case 'D':
        $class_obj = new DifferentialRevision();
        break;
      case 'C':
        $class_obj = new PhabricatorRepositoryCommit();
        break;
      case 'E':
        $class_obj = new ConpherenceThread();
        break;
      default:
        return null;
    }

    return $class_obj->load($receiver_id);
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
