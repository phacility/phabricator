<?php

/**
 * @group conpherence
 */
final class ConpherenceThread extends ConpherenceDAO
  implements PhabricatorPolicyInterface {

  protected $id;
  protected $phid;
  protected $title;
  protected $imagePHID;
  protected $mailKey;

  private $participants;
  private $transactions;
  private $handles;
  private $filePHIDs;
  private $widgetData;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
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

  public function loadImageURI() {
    $src_phid = $this->getImagePHID();

    if ($src_phid) {
      $file = id(new PhabricatorFile())->loadOneWhere('phid = %s', $src_phid);
      if ($file) {
        return $file->getBestURI();
      }
    }

    return PhabricatorUser::getDefaultProfileImageURI();
  }

  public function getDisplayData(PhabricatorUser $user) {
    $transactions = $this->getTransactions();
    $latest_transaction = end($transactions);
    $latest_participant = $latest_transaction->getAuthorPHID();
    $handles = $this->getHandles();
    $latest_handle = $handles[$latest_participant];
    if ($this->getImagePHID()) {
      $img_src = $this->loadImageURI();
    } else {
      $img_src = $latest_handle->getImageURI();
    }
    $title = $this->getTitle();
    if (!$title) {
      $title = $latest_handle->getName();
      unset($handles[$latest_participant]);
    }
    unset($handles[$user->getPHID()]);

    $subtitle = '';
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

    $participants = $this->getParticipants();
    $user_participation = $participants[$user->getPHID()];
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
              48
            );
          }
          // fallthrough intentionally here
        case ConpherenceTransactionType::TYPE_FILES:
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

    return array(
      'title' => $title,
      'subtitle' => $subtitle,
      'unread_count' => $unread_count,
      'epoch' => $latest_transaction->getDateCreated(),
      'image' => $img_src,
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
