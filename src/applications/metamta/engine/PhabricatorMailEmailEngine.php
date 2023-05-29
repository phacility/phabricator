<?php

final class PhabricatorMailEmailEngine
  extends PhabricatorMailMessageEngine {

  public function newMessage() {
    $mailer = $this->getMailer();
    $mail = $this->getMail();

    $message = new PhabricatorMailEmailMessage();

    $from_address = $this->newFromEmailAddress();
    $message->setFromAddress($from_address);

    $reply_address = $this->newReplyToEmailAddress();
    if ($reply_address) {
      $message->setReplyToAddress($reply_address);
    }

    $to_addresses = $this->newToEmailAddresses();
    $cc_addresses = $this->newCCEmailAddresses();

    if (!$to_addresses && !$cc_addresses) {
      $mail->setMessage(
        pht(
          'Message has no valid recipients: all To/CC are disabled, '.
          'invalid, or configured not to receive this mail.'));
      return null;
    }

    // If this email describes a mail processing error, we rate limit outbound
    // messages to each individual address. This prevents messes where
    // something is stuck in a loop or dumps a ton of messages on us suddenly.
    if ($mail->getIsErrorEmail()) {
      $all_recipients = array();
      foreach ($to_addresses as $to_address) {
        $all_recipients[] = $to_address->getAddress();
      }
      foreach ($cc_addresses as $cc_address) {
        $all_recipients[] = $cc_address->getAddress();
      }
      if ($this->shouldRateLimitMail($all_recipients)) {
        $mail->setMessage(
          pht(
            'This is an error email, but one or more recipients have '.
            'exceeded the error email rate limit. Declining to deliver '.
            'message.'));
        return null;
      }
    }

    // Some mailers require a valid "To:" in order to deliver mail. If we
    // don't have any "To:", try to fill it in with a placeholder "To:".
    // If that also fails, move the "Cc:" line to "To:".
    if (!$to_addresses) {
      $void_address = $this->newVoidEmailAddress();
      $to_addresses = array($void_address);
    }

    $to_addresses = $this->getUniqueEmailAddresses($to_addresses);
    $cc_addresses = $this->getUniqueEmailAddresses(
      $cc_addresses,
      $to_addresses);

    $message->setToAddresses($to_addresses);
    $message->setCCAddresses($cc_addresses);

    $attachments = $this->newEmailAttachments();
    $message->setAttachments($attachments);

    $subject = $this->newEmailSubject();
    $message->setSubject($subject);

    $headers = $this->newEmailHeaders();
    foreach ($this->newEmailThreadingHeaders($mailer) as $threading_header) {
      $headers[] = $threading_header;
    }

    $stamps = $mail->getMailStamps();
    if ($stamps) {
      $headers[] = $this->newEmailHeader(
        'X-Phabricator-Stamps',
        implode(' ', $stamps));
    }

    $must_encrypt = $mail->getMustEncrypt();

    $raw_body = $mail->getBody();
    $body = $raw_body;
    if ($must_encrypt) {
      $parts = array();

      $encrypt_uri = $mail->getMustEncryptURI();
      if ($encrypt_uri === null || !strlen($encrypt_uri)) {
        $encrypt_phid = $mail->getRelatedPHID();
        if ($encrypt_phid) {
          $encrypt_uri = urisprintf(
            '/object/%s/',
            $encrypt_phid);
        }
      }

      if ($encrypt_uri !== null && strlen($encrypt_uri)) {
        $parts[] = pht(
          'This secure message is notifying you of a change to this object:');
        $parts[] = PhabricatorEnv::getProductionURI($encrypt_uri);
      }

      $parts[] = pht(
        'The content for this message can only be transmitted over a '.
        'secure channel. To view the message content, follow this '.
        'link:');

      $parts[] = PhabricatorEnv::getProductionURI($mail->getURI());

      $body = implode("\n\n", $parts);
    } else {
      $body = $raw_body;
    }

    $body_limit = PhabricatorEnv::getEnvConfig('metamta.email-body-limit');

    $body = phutil_string_cast($body);
    if (strlen($body) > $body_limit) {
      $body = id(new PhutilUTF8StringTruncator())
        ->setMaximumBytes($body_limit)
        ->truncateString($body);
      $body .= "\n";
      $body .= pht('(This email was truncated at %d bytes.)', $body_limit);
    }
    $message->setTextBody($body);
    $body_limit -= strlen($body);

    // If we sent a different message body than we were asked to, record
    // what we actually sent to make debugging and diagnostics easier.
    if ($body !== $raw_body) {
      $mail->setDeliveredBody($body);
    }

    if ($must_encrypt) {
      $send_html = false;
    } else {
      $send_html = $this->shouldSendHTML();
    }

    if ($send_html) {
      $html_body = $mail->getHTMLBody();
      if (phutil_nonempty_string($html_body)) {
        // NOTE: We just drop the entire HTML body if it won't fit. Safely
        // truncating HTML is hard, and we already have the text body to fall
        // back to.
        if (strlen($html_body) <= $body_limit) {
          $message->setHTMLBody($html_body);
          $body_limit -= strlen($html_body);
        }
      }
    }

    // Pass the headers to the mailer, then save the state so we can show
    // them in the web UI. If the mail must be encrypted, we remove headers
    // which are not on a strict whitelist to avoid disclosing information.
    $filtered_headers = $this->filterHeaders($headers, $must_encrypt);
    $message->setHeaders($filtered_headers);

    $mail->setUnfilteredHeaders($headers);
    $mail->setDeliveredHeaders($headers);

    if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
      $mail->setMessage(
        pht(
          'This software is running in silent mode. See `%s` '.
          'in the configuration to change this setting.',
          'phabricator.silent'));

      return null;
    }

    return $message;
  }

/* -(  Message Components  )------------------------------------------------- */

  private function newFromEmailAddress() {
    $from_address = $this->newDefaultEmailAddress();
    $mail = $this->getMail();

    // If the mail content must be encrypted, always disguise the sender.
    $must_encrypt = $mail->getMustEncrypt();
    if ($must_encrypt) {
      return $from_address;
    }

    // If we have a raw "From" address, use that.
    $raw_from = $mail->getRawFrom();
    if ($raw_from) {
      list($from_email, $from_name) = $raw_from;
      return $this->newEmailAddress($from_email, $from_name);
    }

    // Otherwise, use as much of the information for any sending entity as
    // we can.
    $from_phid = $mail->getFrom();

    $actor = $this->getActor($from_phid);
    if ($actor) {
      $actor_email = $actor->getEmailAddress();
      $actor_name = $actor->getName();
    } else {
      $actor_email = null;
      $actor_name = null;
    }

    $send_as_user = PhabricatorEnv::getEnvConfig('metamta.can-send-as-user');
    if ($send_as_user) {
      if ($actor_email !== null) {
        $from_address->setAddress($actor_email);
      }
    }

    if ($actor_name !== null) {
      $from_address->setDisplayName($actor_name);
    }

    return $from_address;
  }

  private function newReplyToEmailAddress() {
    $mail = $this->getMail();

    $reply_raw = $mail->getReplyTo();
    if (!phutil_nonempty_string($reply_raw)) {
      return null;
    }

    $reply_address = new PhutilEmailAddress($reply_raw);

    // If we have a sending object, change the display name.
    $from_phid = $mail->getFrom();
    $actor = $this->getActor($from_phid);
    if ($actor) {
      $reply_address->setDisplayName($actor->getName());
    }

    // If we don't have a display name, fill in a default.
    $reply_display_name = $reply_address->getDisplayName();
    if ($reply_display_name === null || !strlen($reply_display_name)) {
      $reply_address->setDisplayName(PlatformSymbols::getPlatformServerName());
    }

    return $reply_address;
  }

  private function newToEmailAddresses() {
    $mail = $this->getMail();

    $phids = $mail->getToPHIDs();
    $addresses = $this->newEmailAddressesFromActorPHIDs($phids);

    foreach ($mail->getRawToAddresses() as $raw_address) {
      $addresses[] = new PhutilEmailAddress($raw_address);
    }

    return $addresses;
  }

  private function newCCEmailAddresses() {
    $mail = $this->getMail();
    $phids = $mail->getCcPHIDs();
    return $this->newEmailAddressesFromActorPHIDs($phids);
  }

  private function newEmailAddressesFromActorPHIDs(array $phids) {
    $mail = $this->getMail();
    $phids = $mail->expandRecipients($phids);

    $addresses = array();
    foreach ($phids as $phid) {
      $actor = $this->getActor($phid);
      if (!$actor) {
        continue;
      }

      if (!$actor->isDeliverable()) {
        continue;
      }

      $addresses[] = new PhutilEmailAddress($actor->getEmailAddress());
    }

    return $addresses;
  }

  private function newEmailSubject() {
    $mail = $this->getMail();

    $is_threaded = (bool)$mail->getThreadID();
    $must_encrypt = $mail->getMustEncrypt();

    $subject = array();

    if ($is_threaded) {
      if ($this->shouldAddRePrefix()) {
        $subject[] = 'Re:';
      }
    }

    $subject_prefix = $mail->getSubjectPrefix();
    $subject_prefix = phutil_string_cast($subject_prefix);
    $subject_prefix = trim($subject_prefix);

    $subject[] = $subject_prefix;

    // If mail content must be encrypted, we replace the subject with
    // a generic one.
    if ($must_encrypt) {
      $encrypt_subject = $mail->getMustEncryptSubject();
      if ($encrypt_subject === null || !strlen($encrypt_subject)) {
        $encrypt_subject = pht('Object Updated');
      }
      $subject[] = $encrypt_subject;
    } else {
      $vary_prefix = $mail->getVarySubjectPrefix();
      if (phutil_nonempty_string($vary_prefix)) {
        if ($this->shouldVarySubject()) {
          $subject[] = $vary_prefix;
        }
      }

      $subject[] = $mail->getSubject();
    }

    foreach ($subject as $key => $part) {
      if (!phutil_nonempty_string($part)) {
        unset($subject[$key]);
      }
    }

    $subject = implode(' ', $subject);
    return $subject;
  }

  private function newEmailHeaders() {
    $mail = $this->getMail();

    $headers = array();

    $headers[] = $this->newEmailHeader(
      'X-Phabricator-Sent-This-Message',
      'Yes');
    $headers[] = $this->newEmailHeader(
      'X-Mail-Transport-Agent',
      'MetaMTA');

    // Some clients respect this to suppress OOF and other auto-responses.
    $headers[] = $this->newEmailHeader(
      'X-Auto-Response-Suppress',
      'All');

    $mailtags = $mail->getMailTags();
    if ($mailtags) {
      $tag_header = array();
      foreach ($mailtags as $mailtag) {
        $tag_header[] = '<'.$mailtag.'>';
      }
      $tag_header = implode(', ', $tag_header);
      $headers[] = $this->newEmailHeader(
        'X-Phabricator-Mail-Tags',
        $tag_header);
    }

    $value = $mail->getHeaders();
    foreach ($value as $pair) {
      list($header_key, $header_value) = $pair;

      // NOTE: If we have \n in a header, SES rejects the email.
      $header_value = str_replace("\n", ' ', $header_value);
      $headers[] = $this->newEmailHeader($header_key, $header_value);
    }

    $is_bulk = $mail->getIsBulk();
    if ($is_bulk) {
      $headers[] = $this->newEmailHeader('Precedence', 'bulk');
    }

    if ($mail->getMustEncrypt()) {
      $headers[] = $this->newEmailHeader('X-Phabricator-Must-Encrypt', 'Yes');
    }

    $related_phid = $mail->getRelatedPHID();
    if ($related_phid) {
      $headers[] = $this->newEmailHeader('Thread-Topic', $related_phid);
    }

    $headers[] = $this->newEmailHeader(
      'X-Phabricator-Mail-ID',
      $mail->getID());

    $unique = Filesystem::readRandomCharacters(16);
    $headers[] = $this->newEmailHeader(
      'X-Phabricator-Send-Attempt',
      $unique);

    return $headers;
  }

  private function newEmailThreadingHeaders() {
    $mailer = $this->getMailer();
    $mail = $this->getMail();

    $headers = array();

    $thread_id = $mail->getThreadID();
    if (!phutil_nonempty_string($thread_id)) {
      return $headers;
    }

    $is_first = $mail->getIsFirstMessage();

    // NOTE: Gmail freaks out about In-Reply-To and References which aren't in
    // the form "<string@domain.tld>"; this is also required by RFC 2822,
    // although some clients are more liberal in what they accept.
    $domain = $this->newMailDomain();
    $thread_id = '<'.$thread_id.'@'.$domain.'>';

    if ($is_first && $mailer->supportsMessageIDHeader()) {
      $headers[] = $this->newEmailHeader('Message-ID',  $thread_id);
    } else {
      $in_reply_to = $thread_id;
      $references = array($thread_id);
      $parent_id = $mail->getParentMessageID();
      if ($parent_id) {
        $in_reply_to = $parent_id;
        // By RFC 2822, the most immediate parent should appear last
        // in the "References" header, so this order is intentional.
        $references[] = $parent_id;
      }
      $references = implode(' ', $references);
      $headers[] = $this->newEmailHeader('In-Reply-To', $in_reply_to);
      $headers[] = $this->newEmailHeader('References',  $references);
    }
    $thread_index = $this->generateThreadIndex($thread_id, $is_first);
    $headers[] = $this->newEmailHeader('Thread-Index', $thread_index);

    return $headers;
  }

  private function newEmailAttachments() {
    $mail = $this->getMail();

    // If the mail content must be encrypted, don't add attachments.
    $must_encrypt = $mail->getMustEncrypt();
    if ($must_encrypt) {
      return array();
    }

    return $mail->getAttachments();
  }

/* -(  Preferences  )-------------------------------------------------------- */

  private function shouldAddRePrefix() {
    $preferences = $this->getPreferences();

    $value = $preferences->getSettingValue(
      PhabricatorEmailRePrefixSetting::SETTINGKEY);

    return ($value == PhabricatorEmailRePrefixSetting::VALUE_RE_PREFIX);
  }

  private function shouldVarySubject() {
    $preferences = $this->getPreferences();

    $value = $preferences->getSettingValue(
      PhabricatorEmailVarySubjectsSetting::SETTINGKEY);

    return ($value == PhabricatorEmailVarySubjectsSetting::VALUE_VARY_SUBJECTS);
  }

  private function shouldSendHTML() {
    $preferences = $this->getPreferences();

    $value = $preferences->getSettingValue(
      PhabricatorEmailFormatSetting::SETTINGKEY);

    return ($value == PhabricatorEmailFormatSetting::VALUE_HTML_EMAIL);
  }


/* -(  Utilities  )---------------------------------------------------------- */

  private function newEmailHeader($name, $value) {
    return id(new PhabricatorMailHeader())
      ->setName($name)
      ->setValue($value);
  }

  private function newEmailAddress($address, $name = null) {
    $object = id(new PhutilEmailAddress())
      ->setAddress($address);

    if ($name !== null && strlen($name)) {
      $object->setDisplayName($name);
    }

    return $object;
  }

  public function newDefaultEmailAddress() {
    $raw_address = PhabricatorEnv::getEnvConfig('metamta.default-address');

    if ($raw_address == null || !strlen($raw_address)) {
      $domain = $this->newMailDomain();
      $raw_address = "noreply@{$domain}";
    }

    $address = new PhutilEmailAddress($raw_address);

    if (!phutil_nonempty_string($address->getDisplayName())) {
      $address->setDisplayName(PlatformSymbols::getPlatformServerName());
    }

    return $address;
  }

  public function newVoidEmailAddress() {
    return $this->newDefaultEmailAddress();
  }

  private function newMailDomain() {
    $domain = PhabricatorEnv::getEnvConfig('metamta.reply-handler-domain');
    if ($domain !== null && strlen($domain)) {
      return $domain;
    }

    $install_uri = PhabricatorEnv::getURI('/');
    $install_uri = new PhutilURI($install_uri);

    return $install_uri->getDomain();
  }

  private function filterHeaders(array $headers, $must_encrypt) {
    assert_instances_of($headers, 'PhabricatorMailHeader');

    if (!$must_encrypt) {
      return $headers;
    }

    $whitelist = array(
      'In-Reply-To',
      'Message-ID',
      'Precedence',
      'References',
      'Thread-Index',
      'Thread-Topic',

      'X-Mail-Transport-Agent',
      'X-Auto-Response-Suppress',

      'X-Phabricator-Sent-This-Message',
      'X-Phabricator-Must-Encrypt',
      'X-Phabricator-Mail-ID',
      'X-Phabricator-Send-Attempt',
    );

    // NOTE: The major header we want to drop is "X-Phabricator-Mail-Tags".
    // This header contains a significant amount of meaningful information
    // about the object.

    $whitelist_map = array();
    foreach ($whitelist as $term) {
      $whitelist_map[phutil_utf8_strtolower($term)] = true;
    }

    foreach ($headers as $key => $header) {
      $name = $header->getName();
      $name = phutil_utf8_strtolower($name);

      if (!isset($whitelist_map[$name])) {
        unset($headers[$key]);
      }
    }

    return $headers;
  }

  private function getUniqueEmailAddresses(
    array $addresses,
    array $exclude = array()) {
    assert_instances_of($addresses, 'PhutilEmailAddress');
    assert_instances_of($exclude, 'PhutilEmailAddress');

    $seen = array();

    foreach ($exclude as $address) {
      $seen[$address->getAddress()] = true;
    }

    foreach ($addresses as $key => $address) {
      $raw_address = $address->getAddress();

      if (isset($seen[$raw_address])) {
        unset($addresses[$key]);
        continue;
      }

      $seen[$raw_address] = true;
    }

    return array_values($addresses);
  }

  private function generateThreadIndex($seed, $is_first_mail) {
    // When threading, Outlook ignores the 'References' and 'In-Reply-To'
    // headers that most clients use. Instead, it uses a custom 'Thread-Index'
    // header. The format of this header is something like this (from
    // camel-exchange-folder.c in Evolution Exchange):

    /* A new post to a folder gets a 27-byte-long thread index. (The value
     * is apparently unique but meaningless.) Each reply to a post gets a
     * 32-byte-long thread index whose first 27 bytes are the same as the
     * parent's thread index. Each reply to any of those gets a
     * 37-byte-long thread index, etc. The Thread-Index header contains a
     * base64 representation of this value.
     */

    // The specific implementation uses a 27-byte header for the first email
    // a recipient receives, and a random 5-byte suffix (32 bytes total)
    // thereafter. This means that all the replies are (incorrectly) siblings,
    // but it would be very difficult to keep track of the entire tree and this
    // gets us reasonable client behavior.

    $base = substr(md5($seed), 0, 27);
    if (!$is_first_mail) {
      // Not totally sure, but it seems like outlook orders replies by
      // thread-index rather than timestamp, so to get these to show up in the
      // right order we use the time as the last 4 bytes.
      $base .= ' '.pack('N', time());
    }

    return base64_encode($base);
  }

  private function shouldRateLimitMail(array $all_recipients) {
    try {
      PhabricatorSystemActionEngine::willTakeAction(
        $all_recipients,
        new PhabricatorMetaMTAErrorMailAction(),
        1);
      return false;
    } catch (PhabricatorSystemActionRateLimitException $ex) {
      return true;
    }
  }

}
