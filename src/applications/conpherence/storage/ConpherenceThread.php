<?php

final class ConpherenceThread extends ConpherenceDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorMentionableInterface,
    PhabricatorDestructibleInterface {

  protected $title;
  protected $isRoom = 0;
  protected $messageCount;
  protected $recentParticipantPHIDs = array();
  protected $mailKey;
  protected $viewPolicy;
  protected $editPolicy;
  protected $joinPolicy;

  private $participants = self::ATTACHABLE;
  private $transactions = self::ATTACHABLE;
  private $handles = self::ATTACHABLE;
  private $filePHIDs = self::ATTACHABLE;
  private $widgetData = self::ATTACHABLE;
  private $images = array();

  public static function initializeNewThread(PhabricatorUser $sender) {
    return id(new ConpherenceThread())
      ->setMessageCount(0)
      ->setTitle('')
      ->attachParticipants(array())
      ->attachFilePHIDs(array())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy(PhabricatorPolicies::POLICY_USER)
      ->setJoinPolicy(PhabricatorPolicies::POLICY_USER);
  }

  public static function initializeNewRoom(PhabricatorUser $creator) {

    return id(new ConpherenceThread())
      ->setIsRoom(1)
      ->setMessageCount(0)
      ->setTitle('')
      ->attachParticipants(array())
      ->attachFilePHIDs(array())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy($creator->getPHID())
      ->setJoinPolicy(PhabricatorPolicies::POLICY_USER);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'recentParticipantPHIDs' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255?',
        'isRoom' => 'bool',
        'messageCount' => 'uint64',
        'mailKey' => 'text20',
        'joinPolicy' => 'policy',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_room' => array(
          'columns' => array('isRoom', 'dateModified'),
        ),
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

  public function attachFilePHIDs(array $file_phids) {
    $this->filePHIDs = $file_phids;
    return $this;
  }
  public function getFilePHIDs() {
    return $this->assertAttached($this->filePHIDs);
  }

  public function attachWidgetData(array $widget_data) {
    $this->widgetData = $widget_data;
    return $this;
  }
  public function getWidgetData() {
    return $this->assertAttached($this->widgetData);
  }

  public function getDisplayData(PhabricatorUser $user) {
    if ($this->hasAttachedTransactions()) {
      $transactions = $this->getTransactions();
    } else {
      $transactions = array();
    }
    $set_title = $this->getTitle();

    if ($set_title) {
      $title_mode = 'title';
    } else {
      $title_mode = 'recent';
    }

    if ($transactions) {
      $subtitle_mode = 'message';
    } else {
      $subtitle_mode = 'recent';
    }

    $recent_phids = $this->getRecentParticipantPHIDs();
    $handles = $this->getHandles();
    // Luck has little to do with it really; most recent participant who
    // isn't the user....
    $lucky_phid = null;
    $lucky_index = null;
    $recent_title = null;
    foreach ($recent_phids as $index => $phid) {
      if ($phid == $user->getPHID()) {
        continue;
      }
      $lucky_phid = $phid;
      break;
    }
    reset($recent_phids);

    if ($lucky_phid) {
      $lucky_handle = $handles[$lucky_phid];
    } else {
      // This will be just the user talking to themselves. Weirdo.
      $lucky_handle = reset($handles);
    }

    $img_src = null;
    if ($lucky_handle) {
      $img_src = $lucky_handle->getImageURI();
    }

    if ($title_mode == 'recent' || $subtitle_mode == 'recent') {
      $count = 0;
      $final = false;
      foreach ($recent_phids as $phid) {
        if ($phid == $user->getPHID()) {
          continue;
        }
        $handle = $handles[$phid];
        if ($recent_title) {
          if ($final) {
            $recent_title .= '...';
            break;
          } else {
            $recent_title .= ', ';
          }
        }
        $recent_title .= $handle->getName();
        $count++;
        $final = $count == 3;
      }
    }

    switch ($title_mode) {
      case 'recent':
        $title = $recent_title;
        $js_title = $recent_title;
        break;
      case 'title':
        $title = $js_title = $this->getTitle();
        break;
    }

    $message_title = null;
    if ($subtitle_mode == 'message') {
      $message_transaction = null;
      foreach ($transactions as $transaction) {
        switch ($transaction->getTransactionType()) {
          case PhabricatorTransactions::TYPE_COMMENT:
            $message_transaction = $transaction;
            break 2;
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
    }
    switch ($subtitle_mode) {
      case 'recent':
        $subtitle = $recent_title;
        break;
      case 'message':
        if ($message_title) {
          $subtitle = $message_title;
        } else {
          $subtitle = $recent_title;
        }
        break;
    }

    $user_participation = $this->getParticipantIfExists($user->getPHID());
    if ($user_participation) {
      $user_seen_count = $user_participation->getSeenMessageCount();
    } else {
      $user_seen_count = 0;
    }
    $unread_count = $this->getMessageCount() - $user_seen_count;

    return array(
      'title' => $title,
      'js_title' => $js_title,
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
    if ($this->getIsRoom()) {
      switch ($capability) {
        case PhabricatorPolicyCapability::CAN_VIEW:
          return $this->getViewPolicy();
        case PhabricatorPolicyCapability::CAN_EDIT:
          return $this->getEditPolicy();
        case PhabricatorPolicyCapability::CAN_JOIN:
          return $this->getJoinPolicy();
      }
    }
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    // this bad boy isn't even created yet so go nuts $user
    if (!$this->getID()) {
      return true;
    }

    if ($this->getIsRoom()) {
      switch ($capability) {
        case PhabricatorPolicyCapability::CAN_EDIT:
        case PhabricatorPolicyCapability::CAN_JOIN:
          return false;
      }
    }

    $participants = $this->getParticipants();
    return isset($participants[$user->getPHID()]);
  }

  public function describeAutomaticCapability($capability) {
    if ($this->getIsRoom()) {
      switch ($capability) {
        case PhabricatorPolicyCapability::CAN_VIEW:
          return pht('Participants in a room can always view it.');
          break;
      }
    } else {
      return pht('Participants in a thread can always view and edit it.');
    }
  }

  public static function loadPolicyObjects(
    PhabricatorUser $viewer,
    array $conpherences) {

    assert_instances_of($conpherences, 'ConpherenceThread');

    $grouped = mgroup($conpherences, 'getIsRoom');
    $rooms = idx($grouped, 1, array());

    $policies = array();
    foreach ($rooms as $room) {
      $policies[] = $room->getViewPolicy();
    }
    $policy_objects = array();
    if ($policies) {
      $policy_objects = id(new PhabricatorPolicyQuery())
        ->setViewer($viewer)
        ->withPHIDs($policies)
        ->execute();
    }

    return $policy_objects;
  }

  public function getPolicyIconName(array $policy_objects) {
    assert_instances_of($policy_objects, 'PhabricatorPolicy');

    if ($this->getIsRoom()) {
      $icon = $policy_objects[$this->getViewPolicy()]->getIcon();
    } else if (count($this->getRecentParticipantPHIDs()) > 2) {
      $icon = 'fa-users';
    } else {
      $icon = 'fa-user';
    }
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
