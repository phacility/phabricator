<?php

/**
 * @group conpherence
 */
final class ConpherenceThread extends ConpherenceDAO
  implements PhabricatorPolicyInterface {

  protected $id;
  protected $phid;
  protected $title;
  protected $messageCount;
  protected $recentParticipantPHIDs = array();
  protected $imagePHIDs = array();
  protected $mailKey;

  private $participants;
  private $transactions;
  private $handles;
  private $filePHIDs;
  private $widgetData;
  private $images = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'recentParticipantPHIDs' => self::SERIALIZATION_JSON,
        'imagePHIDs' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_CONP);
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function getImagePHID($size) {
    $image_phids = $this->getImagePHIDs();
    return idx($image_phids, $size);
  }
  public function setImagePHID($phid, $size) {
    $image_phids = $this->getImagePHIDs();
    $image_phids[$size] = $phid;
    return $this->setImagePHIDs($image_phids);
  }

  public function getImage($size) {
    $images = $this->getImages();
    return idx($images, $size);
  }
  public function setImage(PhabricatorFile $file, $size) {
    $files = $this->getImages();
    $files[$size] = $file;
    return $this->setImages($files);
  }
  public function setImages(array $files) {
    $this->images = $files;
    return $this;
  }
  private function getImages() {
    return $this->images;
  }

  public function attachParticipants(array $participants) {
    assert_instances_of($participants, 'ConpherenceParticipant');
    $this->participants = $participants;
    return $this;
  }
  public function getParticipants() {
    if ($this->participants === null) {
      throw new Exception(
        'You must attachParticipants first!'
      );
    }
    return $this->participants;
  }
  public function getParticipant($phid) {
    $participants = $this->getParticipants();
    return $participants[$phid];
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
    if ($this->handles === null) {
      throw new Exception(
        'You must attachHandles first!'
      );
    }
    return $this->handles;
  }

  public function attachTransactions(array $transactions) {
    assert_instances_of($transactions, 'ConpherenceTransaction');
    $this->transactions = $transactions;
    return $this;
  }
  public function getTransactions() {
    if ($this->transactions === null) {
      throw new Exception(
        'You must attachTransactions first!'
      );
    }
    return $this->transactions;
  }

  public function getTransactionsFrom($begin = 0, $amount = null) {
    $length = count($this->transactions);
    if ($amount === null) {
      $amount === $length;
    }
    if ($this->transactions === null) {
      throw new Exception(
        'You must attachTransactions first!'
      );
    }
    return array_slice(
      $this->transactions,
      $length - $begin - $amount,
      $amount);
  }

  public function attachFilePHIDs(array $file_phids) {
    $this->filePHIDs = $file_phids;
    return $this;
  }
  public function getFilePHIDs() {
    if ($this->filePHIDs === null) {
      throw new Exception(
        'You must attachFilePHIDs first!'
      );
    }
    return $this->filePHIDs;
  }

  public function attachWidgetData(array $widget_data) {
    $this->widgetData = $widget_data;
    return $this;
  }
  public function getWidgetData() {
    if ($this->widgetData === null) {
      throw new Exception(
        'You must attachWidgetData first!'
      );
    }
    return $this->widgetData;
  }

  public function loadImageURI($size) {
    $file = $this->getImage($size);

    if ($file) {
      return $file->getBestURI();
    }

    return PhabricatorUser::getDefaultProfileImageURI();
  }

  public function getDisplayData(PhabricatorUser $user, $size) {
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

    $title = $this->getTitle();
    if (!$title) {
      $title = $lucky_handle->getName();
    }

    $image = $this->getImagePHID($size);
    if ($image) {
      $img_src = $this->getImage($size)->getBestURI();
      $img_class = 'custom-';
    } else {
      $img_src = $lucky_handle->getImageURI();
      $img_class = null;
    }

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

    $participants = $this->getParticipants();
    $user_participation = $participants[$user->getPHID()];
    $unread_count = $this->getMessageCount() -
      $user_participation->getSeenMessageCount();

    return array(
      'title' => $title,
      'subtitle' => $subtitle,
      'unread_count' => $unread_count,
      'epoch' => $this->getDateModified(),
      'image' => $img_src,
      'image_class' => $img_class,
    );
  }

/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */
  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    $participants = $this->getParticipants();
    return isset($participants[$user->getPHID()]);
  }

}
