<?php

final class ConpherenceThread extends ConpherenceDAO
  implements PhabricatorPolicyInterface {

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
          'columns' => array('isRoom', 'dateModified'),),
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
  public function getTransactions() {
    return $this->assertAttached($this->transactions);
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
    $recent_phids = $this->getRecentParticipantPHIDs();
    $handles = $this->getHandles();

    // luck has little to do with it really; most recent participant who isn't
    // the user....
    $lucky_phid = null;
    $lucky_index = null;
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
    // this will be just the user talking to themselves. weirdos.
    } else {
      $lucky_handle = reset($handles);
    }

    $title = $js_title = $this->getTitle();
    if (!$title) {
      $title = $lucky_handle->getName();
      $js_title = pht('[No Title]');
    }
    $img_src = $lucky_handle->getImageURI();

    $count = 0;
    $final = false;
    $subtitle = null;
    foreach ($recent_phids as $phid) {
      if ($phid == $user->getPHID()) {
        continue;
      }
      $handle = $handles[$phid];
      if ($subtitle) {
        if ($final) {
          $subtitle .= '...';
          break;
        } else {
          $subtitle .= ', ';
        }
      }
      $subtitle .= $handle->getName();
      $count++;
      $final = $count == 3;
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

}
