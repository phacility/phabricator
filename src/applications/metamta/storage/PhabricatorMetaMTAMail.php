<?php

/**
 * @task recipients   Managing Recipients
 */
final class PhabricatorMetaMTAMail
  extends PhabricatorMetaMTADAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  const RETRY_DELAY   = 5;

  protected $actorPHID;
  protected $parameters = array();
  protected $status;
  protected $message;
  protected $relatedPHID;

  private $recipientExpansionMap;
  private $routingMap;

  public function __construct() {

    $this->status     = PhabricatorMailOutboundStatus::STATUS_QUEUE;
    $this->parameters = array(
      'sensitive' => true,
      'mustEncrypt' => false,
    );

    parent::__construct();
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'parameters'  => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'actorPHID' => 'phid?',
        'status' => 'text32',
        'relatedPHID' => 'phid?',

        // T6203/NULLABILITY
        // This should just be empty if there's no body.
        'message' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'status' => array(
          'columns' => array('status'),
        ),
        'key_actorPHID' => array(
          'columns' => array('actorPHID'),
        ),
        'relatedPHID' => array(
          'columns' => array('relatedPHID'),
        ),
        'key_created' => array(
          'columns' => array('dateCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorMetaMTAMailPHIDType::TYPECONST);
  }

  protected function setParam($param, $value) {
    $this->parameters[$param] = $value;
    return $this;
  }

  protected function getParam($param, $default = null) {
    // Some old mail was saved without parameters because no parameters were
    // set or encoding failed. Recover in these cases so we can perform
    // mail migrations, see T9251.
    if (!is_array($this->parameters)) {
      $this->parameters = array();
    }

    return idx($this->parameters, $param, $default);
  }

  /**
   * These tags are used to allow users to opt out of receiving certain types
   * of mail, like updates when a task's projects change.
   *
   * @param list<const>
   * @return this
   */
  public function setMailTags(array $tags) {
    $this->setParam('mailtags', array_unique($tags));
    return $this;
  }

  public function getMailTags() {
    return $this->getParam('mailtags', array());
  }

  /**
   * In Gmail, conversations will be broken if you reply to a thread and the
   * server sends back a response without referencing your Message-ID, even if
   * it references a Message-ID earlier in the thread. To avoid this, use the
   * parent email's message ID explicitly if it's available. This overwrites the
   * "In-Reply-To" and "References" headers we would otherwise generate. This
   * needs to be set whenever an action is triggered by an email message. See
   * T251 for more details.
   *
   * @param   string The "Message-ID" of the email which precedes this one.
   * @return  this
   */
  public function setParentMessageID($id) {
    $this->setParam('parent-message-id', $id);
    return $this;
  }

  public function getParentMessageID() {
    return $this->getParam('parent-message-id');
  }

  public function getSubject() {
    return $this->getParam('subject');
  }

  public function addTos(array $phids) {
    $phids = array_unique($phids);
    $this->setParam('to', $phids);
    return $this;
  }

  public function addRawTos(array $raw_email) {

    // Strip addresses down to bare emails, since the MailAdapter API currently
    // requires we pass it just the address (like `alincoln@logcabin.org`), not
    // a full string like `"Abraham Lincoln" <alincoln@logcabin.org>`.
    foreach ($raw_email as $key => $email) {
      $object = new PhutilEmailAddress($email);
      $raw_email[$key] = $object->getAddress();
    }

    $this->setParam('raw-to', $raw_email);
    return $this;
  }

  public function addCCs(array $phids) {
    $phids = array_unique($phids);
    $this->setParam('cc', $phids);
    return $this;
  }

  public function setExcludeMailRecipientPHIDs(array $exclude) {
    $this->setParam('exclude', $exclude);
    return $this;
  }

  private function getExcludeMailRecipientPHIDs() {
    return $this->getParam('exclude', array());
  }

  public function setMutedPHIDs(array $muted) {
    $this->setParam('muted', $muted);
    return $this;
  }

  private function getMutedPHIDs() {
    return $this->getParam('muted', array());
  }

  public function setForceHeraldMailRecipientPHIDs(array $force) {
    $this->setParam('herald-force-recipients', $force);
    return $this;
  }

  private function getForceHeraldMailRecipientPHIDs() {
    return $this->getParam('herald-force-recipients', array());
  }

  public function addPHIDHeaders($name, array $phids) {
    $phids = array_unique($phids);
    foreach ($phids as $phid) {
      $this->addHeader($name, '<'.$phid.'>');
    }
    return $this;
  }

  public function addHeader($name, $value) {
    $this->parameters['headers'][] = array($name, $value);
    return $this;
  }

  public function getHeaders() {
    return $this->getParam('headers', array());
  }

  public function addAttachment(PhabricatorMailAttachment $attachment) {
    $this->parameters['attachments'][] = $attachment->toDictionary();
    return $this;
  }

  public function getAttachments() {
    $dicts = $this->getParam('attachments', array());

    $result = array();
    foreach ($dicts as $dict) {
      $result[] = PhabricatorMailAttachment::newFromDictionary($dict);
    }
    return $result;
  }

  public function getAttachmentFilePHIDs() {
    $file_phids = array();

    $dictionaries = $this->getParam('attachments');
    if ($dictionaries) {
      foreach ($dictionaries as $dictionary) {
        $file_phid = idx($dictionary, 'filePHID');
        if ($file_phid) {
          $file_phids[] = $file_phid;
        }
      }
    }

    return $file_phids;
  }

  public function loadAttachedFiles(PhabricatorUser $viewer) {
    $file_phids = $this->getAttachmentFilePHIDs();

    if (!$file_phids) {
      return array();
    }

    return id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs($file_phids)
      ->execute();
  }

  public function setAttachments(array $attachments) {
    assert_instances_of($attachments, 'PhabricatorMailAttachment');
    $this->setParam('attachments', mpull($attachments, 'toDictionary'));
    return $this;
  }

  public function setFrom($from) {
    $this->setParam('from', $from);
    $this->setActorPHID($from);
    return $this;
  }

  public function getFrom() {
    return $this->getParam('from');
  }

  public function setRawFrom($raw_email, $raw_name) {
    $this->setParam('raw-from', array($raw_email, $raw_name));
    return $this;
  }

  public function getRawFrom() {
    return $this->getParam('raw-from');
  }

  public function setReplyTo($reply_to) {
    $this->setParam('reply-to', $reply_to);
    return $this;
  }

  public function getReplyTo() {
    return $this->getParam('reply-to');
  }

  public function setSubject($subject) {
    $this->setParam('subject', $subject);
    return $this;
  }

  public function setSubjectPrefix($prefix) {
    $this->setParam('subject-prefix', $prefix);
    return $this;
  }

  public function getSubjectPrefix() {
    return $this->getParam('subject-prefix');
  }

  public function setVarySubjectPrefix($prefix) {
    $this->setParam('vary-subject-prefix', $prefix);
    return $this;
  }

  public function getVarySubjectPrefix() {
    return $this->getParam('vary-subject-prefix');
  }

  public function setBody($body) {
    $this->setParam('body', $body);
    return $this;
  }

  public function setSensitiveContent($bool) {
    $this->setParam('sensitive', $bool);
    return $this;
  }

  public function hasSensitiveContent() {
    return $this->getParam('sensitive', true);
  }

  public function setMustEncrypt($bool) {
    return $this->setParam('mustEncrypt', $bool);
  }

  public function getMustEncrypt() {
    return $this->getParam('mustEncrypt', false);
  }

  public function setMustEncryptURI($uri) {
    return $this->setParam('mustEncrypt.uri', $uri);
  }

  public function getMustEncryptURI() {
    return $this->getParam('mustEncrypt.uri');
  }

  public function setMustEncryptSubject($subject) {
    return $this->setParam('mustEncrypt.subject', $subject);
  }

  public function getMustEncryptSubject() {
    return $this->getParam('mustEncrypt.subject');
  }

  public function setMustEncryptReasons(array $reasons) {
    return $this->setParam('mustEncryptReasons', $reasons);
  }

  public function getMustEncryptReasons() {
    return $this->getParam('mustEncryptReasons', array());
  }

  public function setMailStamps(array $stamps) {
    return $this->setParam('stamps', $stamps);
  }

  public function getMailStamps() {
    return $this->getParam('stamps', array());
  }

  public function setMailStampMetadata($metadata) {
    return $this->setParam('stampMetadata', $metadata);
  }

  public function getMailStampMetadata() {
    return $this->getParam('stampMetadata', array());
  }

  public function getMailerKey() {
    return $this->getParam('mailer.key');
  }

  public function setTryMailers(array $mailers) {
    return $this->setParam('mailers.try', $mailers);
  }

  public function setHTMLBody($html) {
    $this->setParam('html-body', $html);
    return $this;
  }

  public function getBody() {
    return $this->getParam('body');
  }

  public function getHTMLBody() {
    return $this->getParam('html-body');
  }

  public function setIsErrorEmail($is_error) {
    $this->setParam('is-error', $is_error);
    return $this;
  }

  public function getIsErrorEmail() {
    return $this->getParam('is-error', false);
  }

  public function getToPHIDs() {
    return $this->getParam('to', array());
  }

  public function getRawToAddresses() {
    return $this->getParam('raw-to', array());
  }

  public function getCcPHIDs() {
    return $this->getParam('cc', array());
  }

  public function setMessageType($message_type) {
    return $this->setParam('message.type', $message_type);
  }

  public function getMessageType() {
    return $this->getParam(
      'message.type',
      PhabricatorMailEmailMessage::MESSAGETYPE);
  }



  /**
   * Force delivery of a message, even if recipients have preferences which
   * would otherwise drop the message.
   *
   * This is primarily intended to let users who don't want any email still
   * receive things like password resets.
   *
   * @param bool  True to force delivery despite user preferences.
   * @return this
   */
  public function setForceDelivery($force) {
    $this->setParam('force', $force);
    return $this;
  }

  public function getForceDelivery() {
    return $this->getParam('force', false);
  }

  /**
   * Flag that this is an auto-generated bulk message and should have bulk
   * headers added to it if appropriate. Broadly, this means some flavor of
   * "Precedence: bulk" or similar, but is implementation and configuration
   * dependent.
   *
   * @param bool  True if the mail is automated bulk mail.
   * @return this
   */
  public function setIsBulk($is_bulk) {
    $this->setParam('is-bulk', $is_bulk);
    return $this;
  }

  public function getIsBulk() {
    return $this->getParam('is-bulk');
  }

  /**
   * Use this method to set an ID used for message threading. MetaMTA will
   * set appropriate headers (Message-ID, In-Reply-To, References and
   * Thread-Index) based on the capabilities of the underlying mailer.
   *
   * @param string  Unique identifier, appropriate for use in a Message-ID,
   *                In-Reply-To or References headers.
   * @param bool    If true, indicates this is the first message in the thread.
   * @return this
   */
  public function setThreadID($thread_id, $is_first_message = false) {
    $this->setParam('thread-id', $thread_id);
    $this->setParam('is-first-message', $is_first_message);
    return $this;
  }

  public function getThreadID() {
    return $this->getParam('thread-id');
  }

  public function getIsFirstMessage() {
    return (bool)$this->getParam('is-first-message');
  }

  /**
   * Save a newly created mail to the database. The mail will eventually be
   * delivered by the MetaMTA daemon.
   *
   * @return this
   */
  public function saveAndSend() {
    return $this->save();
  }

  /**
   * @return this
   */
  public function save() {
    if ($this->getID()) {
      return parent::save();
    }

    // NOTE: When mail is sent from CLI scripts that run tasks in-process, we
    // may re-enter this method from within scheduleTask(). The implementation
    // is intended to avoid anything awkward if we end up reentering this
    // method.

    $this->openTransaction();
      // Save to generate a mail ID and PHID.
      $result = parent::save();

      // Write the recipient edges.
      $editor = new PhabricatorEdgeEditor();
      $edge_type = PhabricatorMetaMTAMailHasRecipientEdgeType::EDGECONST;
      $recipient_phids = array_merge(
        $this->getToPHIDs(),
        $this->getCcPHIDs());
      $expanded_phids = $this->expandRecipients($recipient_phids);
      $all_phids = array_unique(array_merge(
        $recipient_phids,
        $expanded_phids));
      foreach ($all_phids as $curr_phid) {
        $editor->addEdge($this->getPHID(), $edge_type, $curr_phid);
      }
      $editor->save();

    $this->saveTransaction();

    // Queue a task to send this mail.
    $mailer_task = PhabricatorWorker::scheduleTask(
      'PhabricatorMetaMTAWorker',
      $this->getID(),
      array(
        'priority' => PhabricatorWorker::PRIORITY_ALERTS,
      ));

    return $result;
  }

  /**
   * Attempt to deliver an email immediately, in this process.
   *
   * @return void
   */
  public function sendNow() {
    if ($this->getStatus() != PhabricatorMailOutboundStatus::STATUS_QUEUE) {
      throw new Exception(pht('Trying to send an already-sent mail!'));
    }

    $mailers = self::newMailers(
      array(
        'outbound' => true,
        'media' => array(
          $this->getMessageType(),
        ),
      ));

    $try_mailers = $this->getParam('mailers.try');
    if ($try_mailers) {
      $mailers = mpull($mailers, null, 'getKey');
      $mailers = array_select_keys($mailers, $try_mailers);
    }

    return $this->sendWithMailers($mailers);
  }

  public static function newMailers(array $constraints) {
    PhutilTypeSpec::checkMap(
      $constraints,
      array(
        'types' => 'optional list<string>',
        'inbound' => 'optional bool',
        'outbound' => 'optional bool',
        'media' => 'optional list<string>',
      ));

    $mailers = array();

    $config = PhabricatorEnv::getEnvConfig('cluster.mailers');

    $adapters = PhabricatorMailAdapter::getAllAdapters();
    $next_priority = -1;

    foreach ($config as $spec) {
      $type = $spec['type'];
      if (!isset($adapters[$type])) {
        throw new Exception(
          pht(
            'Unknown mailer ("%s")!',
            $type));
      }

      $key = $spec['key'];
      $mailer = id(clone $adapters[$type])
        ->setKey($key);

      $priority = idx($spec, 'priority');
      if (!$priority) {
        $priority = $next_priority;
        $next_priority--;
      }
      $mailer->setPriority($priority);

      $defaults = $mailer->newDefaultOptions();
      $options = idx($spec, 'options', array()) + $defaults;
      $mailer->setOptions($options);

      $mailer->setSupportsInbound(idx($spec, 'inbound', true));
      $mailer->setSupportsOutbound(idx($spec, 'outbound', true));

      $media = idx($spec, 'media');
      if ($media !== null) {
        $mailer->setMedia($media);
      }

      $mailers[] = $mailer;
    }

    // Remove mailers with the wrong types.
    if (isset($constraints['types'])) {
      $types = $constraints['types'];
      $types = array_fuse($types);
      foreach ($mailers as $key => $mailer) {
        $mailer_type = $mailer->getAdapterType();
        if (!isset($types[$mailer_type])) {
          unset($mailers[$key]);
        }
      }
    }

    // If we're only looking for inbound mailers, remove mailers with inbound
    // support disabled.
    if (!empty($constraints['inbound'])) {
      foreach ($mailers as $key => $mailer) {
        if (!$mailer->getSupportsInbound()) {
          unset($mailers[$key]);
        }
      }
    }

    // If we're only looking for outbound mailers, remove mailers with outbound
    // support disabled.
    if (!empty($constraints['outbound'])) {
      foreach ($mailers as $key => $mailer) {
        if (!$mailer->getSupportsOutbound()) {
          unset($mailers[$key]);
        }
      }
    }

    // Select only the mailers which can transmit messages with requested media
    // types.
    if (!empty($constraints['media'])) {
      foreach ($mailers as $key => $mailer) {
        $supports_any = false;
        foreach ($constraints['media'] as $medium) {
          if ($mailer->supportsMessageType($medium)) {
            $supports_any = true;
            break;
          }
        }

        if (!$supports_any) {
          unset($mailers[$key]);
        }
      }
    }

    $sorted = array();
    $groups = mgroup($mailers, 'getPriority');
    krsort($groups);
    foreach ($groups as $group) {
      // Reorder services within the same priority group randomly.
      shuffle($group);
      foreach ($group as $mailer) {
        $sorted[] = $mailer;
      }
    }

    return $sorted;
  }

  public function sendWithMailers(array $mailers) {
    if (!$mailers) {
      $any_mailers = self::newMailers(array());

      // NOTE: We can end up here with some custom list of "$mailers", like
      // from a unit test. In that case, this message could be misleading. We
      // can't really tell if the caller made up the list, so just assume they
      // aren't tricking us.

      if ($any_mailers) {
        $void_message = pht(
          'No configured mailers support sending outbound mail.');
      } else {
        $void_message = pht(
          'No mailers are configured.');
      }

      return $this
        ->setStatus(PhabricatorMailOutboundStatus::STATUS_VOID)
        ->setMessage($void_message)
        ->save();
    }

    $actors = $this->loadAllActors();

    // If we're sending one mail to everyone, some recipients will be in
    // "Cc" rather than "To". We'll move them to "To" later (or supply a
    // dummy "To") but need to look for the recipient in either the
    // "To" or "Cc" fields here.
    $target_phid = head($this->getToPHIDs());
    if (!$target_phid) {
      $target_phid = head($this->getCcPHIDs());
    }
    $preferences = $this->loadPreferences($target_phid);

    // Attach any files we're about to send to this message, so the recipients
    // can view them.
    $viewer = PhabricatorUser::getOmnipotentUser();
    $files = $this->loadAttachedFiles($viewer);
    foreach ($files as $file) {
      $file->attachToObject($this->getPHID());
    }

    $type_map = PhabricatorMailExternalMessage::getAllMessageTypes();
    $type = idx($type_map, $this->getMessageType());
    if (!$type) {
      throw new Exception(
        pht(
          'Unable to send message with unknown message type "%s".',
          $type));
    }

    $exceptions = array();
    foreach ($mailers as $mailer) {
      try {
        $message = $type->newMailMessageEngine()
          ->setMailer($mailer)
          ->setMail($this)
          ->setActors($actors)
          ->setPreferences($preferences)
          ->newMessage($mailer);
      } catch (Exception $ex) {
        $exceptions[] = $ex;
        continue;
      }

      if (!$message) {
        // If we don't get a message back, that means the mail doesn't actually
        // need to be sent (for example, because recipients have declined to
        // receive the mail). Void it and return.
        return $this
          ->setStatus(PhabricatorMailOutboundStatus::STATUS_VOID)
          ->save();
      }

      try {
        $mailer->sendMessage($message);
      } catch (PhabricatorMetaMTAPermanentFailureException $ex) {
        // If any mailer raises a permanent failure, stop trying to send the
        // mail with other mailers.
        $this
          ->setStatus(PhabricatorMailOutboundStatus::STATUS_FAIL)
          ->setMessage($ex->getMessage())
          ->save();

        throw $ex;
      } catch (Exception $ex) {
        $exceptions[] = $ex;
        continue;
      }

      // Keep track of which mailer actually ended up accepting the message.
      $mailer_key = $mailer->getKey();
      if ($mailer_key !== null) {
        $this->setParam('mailer.key', $mailer_key);
      }

      // Now that we sent the message, store the final deliverability outcomes
      // and reasoning so we can explain why things happened the way they did.
      $actor_list = array();
      foreach ($actors as $actor) {
        $actor_list[$actor->getPHID()] = array(
          'deliverable' => $actor->isDeliverable(),
          'reasons' => $actor->getDeliverabilityReasons(),
        );
      }
      $this->setParam('actors.sent', $actor_list);
      $this->setParam('routing.sent', $this->getParam('routing'));
      $this->setParam('routingmap.sent', $this->getRoutingRuleMap());

      return $this
        ->setStatus(PhabricatorMailOutboundStatus::STATUS_SENT)
        ->save();
    }

    // If we make it here, no mailer could send the mail but no mailer failed
    // permanently either. We update the error message for the mail, but leave
    // it in the current status (usually, STATUS_QUEUE) and try again later.

    $messages = array();
    foreach ($exceptions as $ex) {
      $messages[] = $ex->getMessage();
    }
    $messages = implode("\n\n", $messages);

    $this
      ->setMessage($messages)
      ->save();

    if (count($exceptions) === 1) {
      throw head($exceptions);
    }

    throw new PhutilAggregateException(
      pht('Encountered multiple exceptions while transmitting mail.'),
      $exceptions);
  }


  public static function shouldMailEachRecipient() {
    return PhabricatorEnv::getEnvConfig('metamta.one-mail-per-recipient');
  }


/* -(  Managing Recipients  )------------------------------------------------ */


  /**
   * Get all of the recipients for this mail, after preference filters are
   * applied. This list has all objects to whom delivery will be attempted.
   *
   * Note that this expands recipients into their members, because delivery
   * is never directly attempted to aggregate actors like projects.
   *
   * @return  list<phid>  A list of all recipients to whom delivery will be
   *                      attempted.
   * @task recipients
   */
  public function buildRecipientList() {
    $actors = $this->loadAllActors();
    $actors = $this->filterDeliverableActors($actors);
    return mpull($actors, 'getPHID');
  }

  public function loadAllActors() {
    $actor_phids = $this->getExpandedRecipientPHIDs();
    return $this->loadActors($actor_phids);
  }

  public function getExpandedRecipientPHIDs() {
    $actor_phids = $this->getAllActorPHIDs();
    return $this->expandRecipients($actor_phids);
  }

  private function getAllActorPHIDs() {
    return array_merge(
      array($this->getParam('from')),
      $this->getToPHIDs(),
      $this->getCcPHIDs());
  }

  /**
   * Expand a list of recipient PHIDs (possibly including aggregate recipients
   * like projects) into a deaggregated list of individual recipient PHIDs.
   * For example, this will expand project PHIDs into a list of the project's
   * members.
   *
   * @param list<phid>  List of recipient PHIDs, possibly including aggregate
   *                    recipients.
   * @return list<phid> Deaggregated list of mailable recipients.
   */
  public function expandRecipients(array $phids) {
    if ($this->recipientExpansionMap === null) {
      $all_phids = $this->getAllActorPHIDs();
      $this->recipientExpansionMap = id(new PhabricatorMetaMTAMemberQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs($all_phids)
        ->execute();
    }

    $results = array();
    foreach ($phids as $phid) {
      foreach ($this->recipientExpansionMap[$phid] as $recipient_phid) {
        $results[$recipient_phid] = $recipient_phid;
      }
    }

    return array_keys($results);
  }

  private function filterDeliverableActors(array $actors) {
    assert_instances_of($actors, 'PhabricatorMetaMTAActor');
    $deliverable_actors = array();
    foreach ($actors as $phid => $actor) {
      if ($actor->isDeliverable()) {
        $deliverable_actors[$phid] = $actor;
      }
    }
    return $deliverable_actors;
  }

  private function loadActors(array $actor_phids) {
    $actor_phids = array_filter($actor_phids);
    $viewer = PhabricatorUser::getOmnipotentUser();

    $actors = id(new PhabricatorMetaMTAActorQuery())
      ->setViewer($viewer)
      ->withPHIDs($actor_phids)
      ->execute();

    if (!$actors) {
      return array();
    }

    if ($this->getForceDelivery()) {
      // If we're forcing delivery, skip all the opt-out checks. We don't
      // bother annotating reasoning on the mail in this case because it should
      // always be obvious why the mail hit this rule (e.g., it is a password
      // reset mail).
      foreach ($actors as $actor) {
        $actor->setDeliverable(PhabricatorMetaMTAActor::REASON_FORCE);
      }
      return $actors;
    }

    // Exclude explicit recipients.
    foreach ($this->getExcludeMailRecipientPHIDs() as $phid) {
      $actor = idx($actors, $phid);
      if (!$actor) {
        continue;
      }
      $actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_RESPONSE);
    }

    // Before running more rules, save a list of the actors who were
    // deliverable before we started running preference-based rules. This stops
    // us from trying to send mail to disabled users just because a Herald rule
    // added them, for example.
    $deliverable = array();
    foreach ($actors as $phid => $actor) {
      if ($actor->isDeliverable()) {
        $deliverable[] = $phid;
      }
    }

    // Exclude muted recipients. We're doing this after saving deliverability
    // so that Herald "Send me an email" actions can still punch through a
    // mute.

    foreach ($this->getMutedPHIDs() as $muted_phid) {
      $muted_actor = idx($actors, $muted_phid);
      if (!$muted_actor) {
        continue;
      }
      $muted_actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_MUTED);
    }

    // For the rest of the rules, order matters. We're going to run all the
    // possible rules in order from weakest to strongest, and let the strongest
    // matching rule win. The weaker rules leave annotations behind which help
    // users understand why the mail was routed the way it was.

    // Exclude the actor if their preferences are set.
    $from_phid = $this->getParam('from');
    $from_actor = idx($actors, $from_phid);
    if ($from_actor) {
      $from_user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($from_phid))
        ->needUserSettings(true)
        ->execute();
      $from_user = head($from_user);
      if ($from_user) {
        $pref_key = PhabricatorEmailSelfActionsSetting::SETTINGKEY;
        $exclude_self = $from_user->getUserSetting($pref_key);
        if ($exclude_self) {
          $from_actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_SELF);
        }
      }
    }

    $all_prefs = id(new PhabricatorUserPreferencesQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withUserPHIDs($actor_phids)
      ->needSyntheticPreferences(true)
      ->execute();
    $all_prefs = mpull($all_prefs, null, 'getUserPHID');

    $value_email = PhabricatorEmailTagsSetting::VALUE_EMAIL;

    // Exclude all recipients who have set preferences to not receive this type
    // of email (for example, a user who says they don't want emails about task
    // CC changes).
    $tags = $this->getParam('mailtags');
    if ($tags) {
      foreach ($all_prefs as $phid => $prefs) {
        $user_mailtags = $prefs->getSettingValue(
          PhabricatorEmailTagsSetting::SETTINGKEY);

        // The user must have elected to receive mail for at least one
        // of the mailtags.
        $send = false;
        foreach ($tags as $tag) {
          if (((int)idx($user_mailtags, $tag, $value_email)) == $value_email) {
            $send = true;
            break;
          }
        }

        if (!$send) {
          $actors[$phid]->setUndeliverable(
            PhabricatorMetaMTAActor::REASON_MAILTAGS);
        }
      }
    }

    foreach ($deliverable as $phid) {
      switch ($this->getRoutingRule($phid)) {
        case PhabricatorMailRoutingRule::ROUTE_AS_NOTIFICATION:
          $actors[$phid]->setUndeliverable(
            PhabricatorMetaMTAActor::REASON_ROUTE_AS_NOTIFICATION);
          break;
        case PhabricatorMailRoutingRule::ROUTE_AS_MAIL:
          $actors[$phid]->setDeliverable(
            PhabricatorMetaMTAActor::REASON_ROUTE_AS_MAIL);
          break;
        default:
          // No change.
          break;
      }
    }

    // If recipients were initially deliverable and were added by "Send me an
    // email" Herald rules, annotate them as such and make them deliverable
    // again, overriding any changes made by the "self mail" and "mail tags"
    // settings.
    $force_recipients = $this->getForceHeraldMailRecipientPHIDs();
    $force_recipients = array_fuse($force_recipients);
    if ($force_recipients) {
      foreach ($deliverable as $phid) {
        if (isset($force_recipients[$phid])) {
          $actors[$phid]->setDeliverable(
            PhabricatorMetaMTAActor::REASON_FORCE_HERALD);
        }
      }
    }

    // Exclude recipients who don't want any mail. This rule is very strong
    // and runs last.
    foreach ($all_prefs as $phid => $prefs) {
      $exclude = $prefs->getSettingValue(
        PhabricatorEmailNotificationsSetting::SETTINGKEY);
      if ($exclude) {
        $actors[$phid]->setUndeliverable(
          PhabricatorMetaMTAActor::REASON_MAIL_DISABLED);
      }
    }

    // Unless delivery was forced earlier (password resets, confirmation mail),
    // never send mail to unverified addresses.
    foreach ($actors as $phid => $actor) {
      if ($actor->getIsVerified()) {
        continue;
      }

      $actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_UNVERIFIED);
    }

    return $actors;
  }

  public function getDeliveredHeaders() {
    return $this->getParam('headers.sent');
  }

  public function setDeliveredHeaders(array $headers) {
    $headers = $this->flattenHeaders($headers);
    return $this->setParam('headers.sent', $headers);
  }

  public function getUnfilteredHeaders() {
    $unfiltered = $this->getParam('headers.unfiltered');

    if ($unfiltered === null) {
      // Older versions of Phabricator did not filter headers, and thus did
      // not record unfiltered headers. If we don't have unfiltered header
      // data just return the delivered headers for compatibility.
      return $this->getDeliveredHeaders();
    }

    return $unfiltered;
  }

  public function setUnfilteredHeaders(array $headers) {
    $headers = $this->flattenHeaders($headers);
    return $this->setParam('headers.unfiltered', $headers);
  }

  private function flattenHeaders(array $headers) {
    assert_instances_of($headers, 'PhabricatorMailHeader');

    $list = array();
    foreach ($list as $header) {
      $list[] = array(
        $header->getName(),
        $header->getValue(),
      );
    }

    return $list;
  }

  public function getDeliveredActors() {
    return $this->getParam('actors.sent');
  }

  public function getDeliveredRoutingRules() {
    return $this->getParam('routing.sent');
  }

  public function getDeliveredRoutingMap() {
    return $this->getParam('routingmap.sent');
  }

  public function getDeliveredBody() {
    return $this->getParam('body.sent');
  }

  public function setDeliveredBody($body) {
    return $this->setParam('body.sent', $body);
  }

  public function getURI() {
    return '/mail/detail/'.$this->getID().'/';
  }


/* -(  Routing  )------------------------------------------------------------ */


  public function addRoutingRule($routing_rule, $phids, $reason_phid) {
    $routing = $this->getParam('routing', array());
    $routing[] = array(
      'routingRule' => $routing_rule,
      'phids' => $phids,
      'reasonPHID' => $reason_phid,
    );
    $this->setParam('routing', $routing);

    // Throw the routing map away so we rebuild it.
    $this->routingMap = null;

    return $this;
  }

  private function getRoutingRule($phid) {
    $map = $this->getRoutingRuleMap();

    $info = idx($map, $phid, idx($map, 'default'));
    if ($info) {
      return idx($info, 'rule');
    }

    return null;
  }

  private function getRoutingRuleMap() {
    if ($this->routingMap === null) {
      $map = array();

      $routing = $this->getParam('routing', array());
      foreach ($routing as $route) {
        $phids = $route['phids'];
        if ($phids === null) {
          $phids = array('default');
        }

        foreach ($phids as $phid) {
          $new_rule = $route['routingRule'];

          $current_rule = idx($map, $phid);
          if ($current_rule === null) {
            $is_stronger = true;
          } else {
            $is_stronger = PhabricatorMailRoutingRule::isStrongerThan(
              $new_rule,
              $current_rule);
          }

          if ($is_stronger) {
            $map[$phid] = array(
              'rule' => $new_rule,
              'reason' => $route['reasonPHID'],
            );
          }
        }
      }

      $this->routingMap = $map;
    }

    return $this->routingMap;
  }

/* -(  Preferences  )-------------------------------------------------------- */


  private function loadPreferences($target_phid) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    if (self::shouldMailEachRecipient()) {
      $preferences = id(new PhabricatorUserPreferencesQuery())
        ->setViewer($viewer)
        ->withUserPHIDs(array($target_phid))
        ->needSyntheticPreferences(true)
        ->executeOne();
      if ($preferences) {
        return $preferences;
      }
    }

    return PhabricatorUserPreferences::loadGlobalPreferences($viewer);
  }

  public function shouldRenderMailStampsInBody($viewer) {
    $preferences = $this->loadPreferences($viewer->getPHID());
    $value = $preferences->getSettingValue(
      PhabricatorEmailStampsSetting::SETTINGKEY);

    return ($value == PhabricatorEmailStampsSetting::VALUE_BODY_STAMPS);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    $actor_phids = $this->getExpandedRecipientPHIDs();
    return in_array($viewer->getPHID(), $actor_phids);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'The mail sender and message recipients can always see the mail.');
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $files = $this->loadAttachedFiles($engine->getViewer());
    foreach ($files as $file) {
      $engine->destroyObject($file);
    }

    $this->delete();
  }

}
