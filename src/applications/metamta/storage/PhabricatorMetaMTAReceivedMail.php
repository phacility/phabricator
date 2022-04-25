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

  public function newTargetAddresses() {
    $raw_addresses = array();

    foreach ($this->getToAddresses() as $raw_address) {
      $raw_addresses[] = $raw_address;
    }

    foreach ($this->getCCAddresses() as $raw_address) {
      $raw_addresses[] = $raw_address;
    }

    $raw_addresses = array_unique($raw_addresses);

    $addresses = array();
    foreach ($raw_addresses as $raw_address) {
      $addresses[] = new PhutilEmailAddress($raw_address);
    }

    return $addresses;
  }

  public function loadAllRecipientPHIDs() {
    $addresses = $this->newTargetAddresses();

    // See T13317. Don't allow reserved addresses (like "noreply@...") to
    // match user PHIDs.
    foreach ($addresses as $key => $address) {
      if (PhabricatorMailUtil::isReservedAddress($address)) {
        unset($addresses[$key]);
      }
    }

    if (!$addresses) {
      return array();
    }

    $address_strings = array();
    foreach ($addresses as $address) {
      $address_strings[] = phutil_string_cast($address->getAddress());
    }

    // See T13317. If a verified email address is in the "To" or "Cc" line,
    // we'll count the user who owns that address as a recipient.

    // We require the address be verified because we'll trigger behavior (like
    // adding subscribers) based on the recipient list, and don't want to add
    // Alice as a subscriber if she adds an unverified "internal-bounces@"
    // address to her account and this address gets caught in the crossfire.
    // In the best case this is confusing; in the worst case it could
    // some day give her access to objects she can't see.

    $recipients = id(new PhabricatorUserEmail())
      ->loadAllWhere(
        'address IN (%Ls) AND isVerified = 1',
        $address_strings);

    $recipient_phids = mpull($recipients, 'getUserPHID');

    return $recipient_phids;
  }

  public function processReceivedMail() {
    $viewer = $this->getViewer();

    $sender = null;
    try {
      $this->dropMailFromPhabricator();
      $this->dropMailAlreadyReceived();
      $this->dropEmptyMail();

      $sender = $this->loadSender();
      if ($sender) {
        $this->setAuthorPHID($sender->getPHID());

        // If we've identified the sender, mark them as the author of any
        // attached files. We do this before we validate them (below), since
        // they still authored these files even if their account is not allowed
        // to interact via email.

        $attachments = $this->getAttachments();
        if ($attachments) {
          $files = id(new PhabricatorFileQuery())
            ->setViewer($viewer)
            ->withPHIDs($attachments)
            ->execute();
          foreach ($files as $file) {
            $file->setAuthorPHID($sender->getPHID())->save();
          }
        }

        $this->validateSender($sender);
      }

      $receivers = id(new PhutilClassMapQuery())
        ->setAncestorClass('PhabricatorMailReceiver')
        ->setFilterMethod('isEnabled')
        ->execute();

      $reserved_recipient = null;
      $targets = $this->newTargetAddresses();
      foreach ($targets as $key => $target) {
        // Never accept any reserved address as a mail target. This prevents
        // security issues around "hostmaster@" and bad behavior with
        // "noreply@".
        if (PhabricatorMailUtil::isReservedAddress($target)) {
          if (!$reserved_recipient) {
            $reserved_recipient = $target;
          }
          unset($targets[$key]);
          continue;
        }

        // See T13234. Don't process mail if a user has attached this address
        // to their account.
        if (PhabricatorMailUtil::isUserAddress($target)) {
          unset($targets[$key]);
          continue;
        }
      }

      $any_accepted = false;
      $receiver_exception = null;
      foreach ($receivers as $receiver) {
        $receiver = id(clone $receiver)
          ->setViewer($viewer);

        if ($sender) {
          $receiver->setSender($sender);
        }

        foreach ($targets as $target) {
          try {
            if (!$receiver->canAcceptMail($this, $target)) {
              continue;
            }

            $any_accepted = true;

            $receiver->receiveMail($this, $target);
          } catch (Exception $ex) {
            // If receivers raise exceptions, we'll keep the first one in hope
            // that it points at a root cause.
            if (!$receiver_exception) {
              $receiver_exception = $ex;
            }
          }
        }
      }

      if ($receiver_exception) {
        throw $receiver_exception;
      }


      if (!$any_accepted) {
        if ($reserved_recipient) {
          // If nothing accepted the mail, we normally raise an error to help
          // users who mistakenly send mail to "barges@" instead of "bugs@".

          // However, if the recipient list included a reserved recipient, we
          // don't bounce the mail with an error.

          // The intent here is that if a user does a "Reply All" and includes
          // "From: noreply@phabricator" in the receipient list, we just want
          // to drop the mail rather than send them an unhelpful bounce message.

          throw new PhabricatorMetaMTAReceivedMailProcessingException(
            MetaMTAReceivedMailStatus::STATUS_RESERVED,
            pht(
              'No application handled this mail. This mail was sent to a '.
              'reserved recipient ("%s") so bounces are suppressed.',
              (string)$reserved_recipient));
        } else if (!$sender) {
          // NOTE: Currently, we'll always drop this mail (since it's headed to
          // an unverified recipient). See T12237. These details are still
          // useful because they'll appear in the mail logs and Mail web UI.

          throw new PhabricatorMetaMTAReceivedMailProcessingException(
            MetaMTAReceivedMailStatus::STATUS_UNKNOWN_SENDER,
            pht(
              'This email was sent from an email address ("%s") that is not '.
              'associated with a registered user account. To interact via '.
              'email, add this address to your account.',
              (string)$this->newFromAddress()));
        } else {
          throw new PhabricatorMetaMTAReceivedMailProcessingException(
            MetaMTAReceivedMailStatus::STATUS_NO_RECEIVERS,
            pht(
              'This mail can not be processed because no application '.
              'knows how to handle it. Check that the address you sent it to '.
              'is correct.'));
        }
      }
    } catch (PhabricatorMetaMTAReceivedMailProcessingException $ex) {
      switch ($ex->getStatusCode()) {
        case MetaMTAReceivedMailStatus::STATUS_DUPLICATE:
        case MetaMTAReceivedMailStatus::STATUS_FROM_PHABRICATOR:
          // Don't send an error email back in these cases, since they're
          // very unlikely to be the sender's fault.
          break;
        case MetaMTAReceivedMailStatus::STATUS_RESERVED:
          // This probably is the sender's fault, but it's likely an accident
          // that we received the mail at all.
          break;
        case MetaMTAReceivedMailStatus::STATUS_EMPTY_IGNORED:
          // This error is explicitly ignored.
          break;
        default:
          $this->sendExceptionMail($ex, $sender);
          break;
      }

      $this
        ->setStatus($ex->getStatusCode())
        ->setMessage($ex->getMessage())
        ->save();
      return $this;
    } catch (Exception $ex) {
      $this->sendExceptionMail($ex, $sender);

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

    if (phutil_nonempty_string($addresses)) {
      foreach (explode(',', $addresses) as $address) {
        $raw_addresses[] = $this->getRawEmailAddress($address);
      }
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
        "Ignoring email with '%s' header to avoid loops.",
        'X-Phabricator-Sent-This-Message'));
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

  private function dropEmptyMail() {
    $body = $this->getCleanTextBody();
    $attachments = $this->getAttachments();

    if (strlen($body) || $attachments) {
      return;
    }

    // Only send an error email if the user is talking to just Phabricator.
    // We can assume if there is only one "To" address it is a Phabricator
    // address since this code is running and everything.
    $is_direct_mail = (count($this->getToAddresses()) == 1) &&
                      (count($this->getCCAddresses()) == 0);

    if ($is_direct_mail) {
      $status_code = MetaMTAReceivedMailStatus::STATUS_EMPTY;
    } else {
      $status_code = MetaMTAReceivedMailStatus::STATUS_EMPTY_IGNORED;
    }

    throw new PhabricatorMetaMTAReceivedMailProcessingException(
      $status_code,
      pht(
        'Your message does not contain any body text or attachments, so '.
        'this server can not do anything useful with it. Make sure comment '.
        'text appears at the top of your message: quoted replies, inline '.
        'text, and signatures are discarded and ignored.'));
  }

  private function sendExceptionMail(
    Exception $ex,
    PhabricatorUser $viewer = null) {

    // If we've failed to identify a legitimate sender, we don't send them
    // an error message back. We want to avoid sending mail to unverified
    // addresses. See T12491.
    if (!$viewer) {
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

    // On the MimeMailParser pathway, we arrive here with a list value for
    // headers that appeared multiple times in the original mail. Be
    // accommodating until header handling gets straightened out.

    $headers = array();
    foreach ($this->headers as $key => $values) {
      if (!is_array($values)) {
        $values = array($values);
      }
      foreach ($values as $value) {
        $headers[] = pht('%s: %s', $key, $value);
      }
    }
    $headers = implode("\n", $headers);

    $body = pht(<<<EOBODY
Your email to %s was not processed, because an error occurred while
trying to handle it:

%s

-- Original Message Body -----------------------------------------------------

%s

-- Original Message Headers --------------------------------------------------

%s

EOBODY
,
      PlatformSymbols::getPlatformServerName(),
      wordwrap($description, 78),
      $this->getRawTextBody(),
      $headers);

    $mail = id(new PhabricatorMetaMTAMail())
      ->setIsErrorEmail(true)
      ->setSubject($title)
      ->addTos(array($viewer->getPHID()))
      ->setBody($body)
      ->saveAndSend();
  }

  public function newContentSource() {
    return PhabricatorContentSource::newForSource(
      PhabricatorEmailContentSource::SOURCECONST,
      array(
        'id' => $this->getID(),
      ));
  }

  public function newFromAddress() {
    $raw_from = $this->getHeader('From');

    if (strlen($raw_from)) {
      return new PhutilEmailAddress($raw_from);
    }

    return null;
  }

  private function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  /**
   * Identify the sender's user account for a piece of received mail.
   *
   * Note that this method does not validate that the sender is who they say
   * they are, just that they've presented some credential which corresponds
   * to a recognizable user.
   */
  private function loadSender() {
    $viewer = $this->getViewer();

    // Try to identify the user based on their "From" address.
    $from_address = $this->newFromAddress();
    if ($from_address) {
      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withEmails(array($from_address->getAddress()))
        ->executeOne();
      if ($user) {
        return $user;
      }
    }

    return null;
  }

  private function validateSender(PhabricatorUser $sender) {
    $failure_reason = null;
    if ($sender->getIsDisabled()) {
      $failure_reason = pht(
        'Your account ("%s") is disabled, so you can not interact with '.
        'over email.',
        $sender->getUsername());
    } else if ($sender->getIsStandardUser()) {
      if (!$sender->getIsApproved()) {
        $failure_reason = pht(
          'Your account ("%s") has not been approved yet. You can not '.
          'interact over email until your account is approved.',
          $sender->getUsername());
      } else if (PhabricatorUserEmail::isEmailVerificationRequired() &&
               !$sender->getIsEmailVerified()) {
        $failure_reason = pht(
          'You have not verified the email address for your account ("%s"). '.
          'You must verify your email address before you can interact over '.
          'email.',
          $sender->getUsername());
      }
    }

    if ($failure_reason) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_DISABLED_SENDER,
        $failure_reason);
    }
  }

}
