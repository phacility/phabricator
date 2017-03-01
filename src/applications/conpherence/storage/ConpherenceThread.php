<?php

final class ConpherenceThread extends ConpherenceDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorMentionableInterface,
    PhabricatorDestructibleInterface,
    PhabricatorNgramsInterface {

  protected $title;
  protected $topic;
  protected $profileImagePHID;
  protected $messageCount;
  protected $recentParticipantPHIDs = array();
  protected $mailKey;
  protected $viewPolicy;
  protected $editPolicy;
  protected $joinPolicy;

  private $participants = self::ATTACHABLE;
  private $transactions = self::ATTACHABLE;
  private $profileImageFile = self::ATTACHABLE;
  private $handles = self::ATTACHABLE;

  public static function initializeNewRoom(PhabricatorUser $sender) {
    $default_policy = id(new ConpherenceThreadMembersPolicyRule())
      ->getObjectPolicyFullKey();
    return id(new ConpherenceThread())
      ->setMessageCount(0)
      ->setTitle('')
      ->setTopic('')
      ->attachParticipants(array())
      ->setViewPolicy($default_policy)
      ->setEditPolicy($default_policy)
      ->setJoinPolicy($default_policy);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'recentParticipantPHIDs' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255?',
        'topic' => 'text255',
        'messageCount' => 'uint64',
        'mailKey' => 'text20',
        'joinPolicy' => 'policy',
        'profileImagePHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorConpherenceThreadPHIDType::TYPECONST);
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function getMonogram() {
    return 'Z'.$this->getID();
  }

  public function attachParticipants(array $participants) {
    assert_instances_of($participants, 'ConpherenceParticipant');
    $this->participants = $participants;
    return $this;
  }

  public function getParticipants() {
    return $this->assertAttached($this->participants);
  }

  public function getParticipant($phid) {
    $participants = $this->getParticipants();
    return $participants[$phid];
  }

  public function getParticipantIfExists($phid, $default = null) {
    $participants = $this->getParticipants();
    return idx($participants, $phid, $default);
  }

  public function getParticipantPHIDs() {
    $participants = $this->getParticipants();
    return array_keys($participants);
  }

  public function attachHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function getHandles() {
    return $this->assertAttached($this->handles);
  }

  public function attachTransactions(array $transactions) {
    assert_instances_of($transactions, 'ConpherenceTransaction');
    $this->transactions = $transactions;
    return $this;
  }

  public function getTransactions($assert_attached = true) {
    return $this->assertAttached($this->transactions);
  }

  public function hasAttachedTransactions() {
    return $this->transactions !== self::ATTACHABLE;
  }

  public function getTransactionsFrom($begin = 0, $amount = null) {
    $length = count($this->transactions);

    return array_slice(
      $this->getTransactions(),
      $length - $begin - $amount,
      $amount);
  }

  public function getProfileImageURI() {
    return $this->getProfileImageFile()->getBestURI();
  }

  public function attachProfileImageFile(PhabricatorFile $file) {
    $this->profileImageFile = $file;
    return $this;
  }

  public function getProfileImageFile() {
    return $this->assertAttached($this->profileImageFile);
  }

  /**
   * Get a thread title which doesn't require handles to be attached.
   *
   * This is a less rich title than @{method:getDisplayTitle}, but does not
   * require handles to be attached. We use it to build thread handles without
   * risking cycles or recursion while querying.
   *
   * @return string Lower quality human-readable title.
   */
  public function getStaticTitle() {
    $title = $this->getTitle();
    if (strlen($title)) {
      return $title;
    }

    return pht('Private Room');
  }

  /**
   * Get the thread's display title for a user.
   *
   * If a thread doesn't have a title set, this will return a string describing
   * recent participants.
   *
   * @param PhabricatorUser Viewer.
   * @return string Thread title.
   */
  public function getDisplayTitle(PhabricatorUser $viewer) {
    $title = $this->getTitle();
    if (strlen($title)) {
      return $title;
    }

    return $this->getRecentParticipantsString($viewer);
  }


  /**
   * Get recent participants (other than the viewer) as a string.
   *
   * For example, this method might return "alincoln, htaft, gwashington...".
   *
   * @param PhabricatorUser Viewer.
   * @return string Description of other participants.
   */
  private function getRecentParticipantsString(PhabricatorUser $viewer) {
    $handles = $this->getHandles();
    $phids = $this->getOtherRecentParticipantPHIDs($viewer);

    if (count($phids) == 0) {
      $phids[] = $viewer->getPHID();
      $more = false;
    } else {
      $limit = 3;
      $more = (count($phids) > $limit);
      $phids = array_slice($phids, 0, $limit);
    }

    $names = array_select_keys($handles, $phids);
    $names = mpull($names, 'getName');
    $names = implode(', ', $names);

    if ($more) {
      $names = $names.'...';
    }

    return $names;
  }


  /**
   * Get PHIDs for recent participants who are not the viewer.
   *
   * @param PhabricatorUser Viewer.
   * @return list<phid> Participants who are not the viewer.
   */
  private function getOtherRecentParticipantPHIDs(PhabricatorUser $viewer) {
    $phids = $this->getRecentParticipantPHIDs();
    $phids = array_fuse($phids);
    unset($phids[$viewer->getPHID()]);
    return array_values($phids);
  }


  public function getDisplayData(PhabricatorUser $viewer) {
    $handles = $this->getHandles();

    if ($this->hasAttachedTransactions()) {
      $transactions = $this->getTransactions();
    } else {
      $transactions = array();
    }

    if ($transactions) {
      $subtitle_mode = 'message';
    } else {
      $subtitle_mode = 'recent';
    }

    $lucky_phid = head($this->getOtherRecentParticipantPHIDs($viewer));
    if ($lucky_phid) {
      $lucky_handle = $handles[$lucky_phid];
    } else {
      // This will be just the user talking to themselves. Weirdo.
      $lucky_handle = reset($handles);
    }

    $img_src = $this->getProfileImageURI();

    $message_title = null;
    if ($subtitle_mode == 'message') {
      $message_transaction = null;
      $action_transaction = null;
      foreach ($transactions as $transaction) {
        if ($message_transaction || $action_transaction) {
          break;
        }
        switch ($transaction->getTransactionType()) {
          case PhabricatorTransactions::TYPE_COMMENT:
            $message_transaction = $transaction;
            break;
          case ConpherenceTransaction::TYPE_TITLE:
          case ConpherenceTransaction::TYPE_TOPIC:
          case ConpherenceTransaction::TYPE_PICTURE:
          case ConpherenceTransaction::TYPE_PARTICIPANTS:
            $action_transaction = $transaction;
            break;
          default:
            break;
        }
      }
      if ($message_transaction) {
        $message_handle = $handles[$message_transaction->getAuthorPHID()];
        $message_title = sprintf(
          '%s: %s',
          $message_handle->getName(),
          id(new PhutilUTF8StringTruncator())
            ->setMaximumGlyphs(60)
            ->truncateString(
              $message_transaction->getComment()->getContent()));
      }
      if ($action_transaction) {
        $message_title = id(clone $action_transaction)
          ->setRenderingTarget(PhabricatorApplicationTransaction::TARGET_TEXT)
          ->getTitle();
      }
    }
    switch ($subtitle_mode) {
      case 'recent':
        $subtitle = $this->getRecentParticipantsString($viewer);
        break;
      case 'message':
        if ($message_title) {
          $subtitle = $message_title;
        } else {
          $subtitle = $this->getRecentParticipantsString($viewer);
        }
        break;
    }

    $user_participation = $this->getParticipantIfExists($viewer->getPHID());
    if ($user_participation) {
      $user_seen_count = $user_participation->getSeenMessageCount();
    } else {
      $user_seen_count = 0;
    }
    $unread_count = $this->getMessageCount() - $user_seen_count;

    $title = $this->getDisplayTitle($viewer);
    $topic = $this->getTopic();

    return array(
      'title' => $title,
      'topic' => $topic,
      'subtitle' => $subtitle,
      'unread_count' => $unread_count,
      'epoch' => $this->getDateModified(),
      'image' => $img_src,
    );
  }


/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
      PhabricatorPolicyCapability::CAN_JOIN,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
      case PhabricatorPolicyCapability::CAN_JOIN:
        return $this->getJoinPolicy();
    }
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    // this bad boy isn't even created yet so go nuts $user
    if (!$this->getID()) {
      return true;
    }

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
      case PhabricatorPolicyCapability::CAN_JOIN:
        return false;
    }

    $participants = $this->getParticipants();
    return isset($participants[$user->getPHID()]);
  }

  public function describeAutomaticCapability($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return pht('Participants in a room can always view it.');
        break;
    }
  }

  public static function loadViewPolicyObjects(
    PhabricatorUser $viewer,
    array $conpherences) {

    assert_instances_of($conpherences, __CLASS__);

    $policies = array();
    foreach ($conpherences as $room) {
      $policies[$room->getViewPolicy()] = 1;
    }
    $policy_objects = array();
    if ($policies) {
      $policy_objects = id(new PhabricatorPolicyQuery())
        ->setViewer($viewer)
        ->withPHIDs(array_keys($policies))
        ->execute();
    }

    return $policy_objects;
  }

  public function getPolicyIconName(array $policy_objects) {
    assert_instances_of($policy_objects, 'PhabricatorPolicy');

    $icon = $policy_objects[$this->getViewPolicy()]->getIcon();
    return $icon;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new ConpherenceEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new ConpherenceTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }

/* -(  PhabricatorNgramInterface  )------------------------------------------ */


  public function newNgrams() {
    return array(
      id(new ConpherenceThreadTitleNgrams())
        ->setValue($this->getTitle()),
      );
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();

      $participants = id(new ConpherenceParticipant())
        ->loadAllWhere('conpherencePHID = %s', $this->getPHID());
      foreach ($participants as $participant) {
        $participant->delete();
      }

    $this->saveTransaction();

  }
}
