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
    $transactions = $this->getTransactions();

    $handles = $this->getHandles();
    // we don't want to show the user unless they are babbling to themselves
    if (count($handles) > 1) {
      unset($handles[$user->getPHID()]);
    }

    $participants = $this->getParticipants();
    $user_participation = $participants[$user->getPHID()];
    $latest_transaction = null;
    $title = $this->getTitle();
    $subtitle = '';
    $img_src = null;
    $img_class = null;
    if ($this->getImagePHID($size)) {
      $img_src = $this->getImage($size)->getBestURI();
      $img_class = 'custom-';
    }
    $unread_count = 0;
    $max_count = 10;
    $snippet = null;
    if (!$user_participation->isUpToDate()) {
      $behind_transaction_phid =
        $user_participation->getBehindTransactionPHID();
    } else {
      $behind_transaction_phid = null;
    }
    foreach (array_reverse($transactions) as $transaction) {
      switch ($transaction->getTransactionType()) {
        case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        case ConpherenceTransactionType::TYPE_TITLE:
        case ConpherenceTransactionType::TYPE_PICTURE:
          continue 2;
        case PhabricatorTransactions::TYPE_COMMENT:
          if ($snippet === null) {
            $snippet = phutil_utf8_shorten(
              $transaction->getComment()->getContent(),
              48);
            if ($transaction->getAuthorPHID() == $user->getPHID()) {
              $snippet = "\xE2\x86\xB0  " . $snippet;
            }
          }
          // fallthrough intentionally here
        case ConpherenceTransactionType::TYPE_FILES:
          if (!$latest_transaction) {
            $latest_transaction = $transaction;
          }
          $latest_participant_phid = $transaction->getAuthorPHID();
          if ((!$title || !$img_src) &&
                $latest_participant_phid != $user->getPHID()) {
            $latest_handle = $handles[$latest_participant_phid];
            if (!$img_src) {
              $img_src = $latest_handle->getImageURI();
            }
            if (!$title) {
              $title = $latest_handle->getName();
              // (maybs) used the pic, definitely used the name -- discard
              unset($handles[$latest_participant_phid]);
            }
          }
          if ($behind_transaction_phid) {
            $unread_count++;
            if ($transaction->getPHID() == $behind_transaction_phid) {
              break 2;
            }
          }
          if ($unread_count > $max_count) {
            break 2;
          }
          break;
        default:
          continue 2;
      }
      if ($snippet && !$behind_transaction_phid) {
        break;
      }
    }
    if ($unread_count > $max_count) {
      $unread_count = $max_count.'+';
    }

    // This happens if the user has been babbling, maybs just to themselves,
    // but enough un-responded to transactions for our SQL limit would
    // hit this too... Also happens on new threads since only the first
    // author has participated.
    // ...so just pick a different handle in these cases.
    $some_handle = reset($handles);
    if (!$img_src) {
      $img_src = $some_handle->getImageURI();
    }
    if (!$title) {
      $title = $some_handle->getName();
    }

    $count = 0;
    $final = false;
    foreach ($handles as $handle) {
      if ($handle->getType() != PhabricatorPHIDConstants::PHID_TYPE_USER) {
        continue;
      }
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

    return array(
      'title' => $title,
      'subtitle' => $subtitle,
      'unread_count' => $unread_count,
      'epoch' => $latest_transaction->getDateCreated(),
      'image' => $img_src,
      'image_class' => $img_class,
      'snippet' => $snippet,
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
