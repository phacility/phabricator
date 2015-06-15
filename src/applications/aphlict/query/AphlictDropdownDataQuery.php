<?php

final class AphlictDropdownDataQuery extends Phobject {

  private $viewer;
  private $notificationData;
  private $conpherenceData;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  private function setNotificationData(array $data) {
    $this->notificationData = $data;
    return $this;
  }

  public function getNotificationData() {
    if ($this->notificationData === null) {
      throw new Exception(pht('You must %s first!', 'execute()'));
    }
    return $this->notificationData;
  }

  private function setConpherenceData(array $data) {
    $this->conpherenceData = $data;
    return $this;
  }

  public function getConpherenceData() {
    if ($this->conpherenceData === null) {
      throw new Exception(pht('You must %s first!', 'execute()'));
    }
    return $this->conpherenceData;
  }

  public function execute() {
    $viewer = $this->getViewer();

    $conpherence_app = 'PhabricatorConpherenceApplication';
    $is_c_installed = PhabricatorApplication::isClassInstalledForViewer(
      $conpherence_app,
      $viewer);
    $raw_message_count_number = null;
    $message_count_number = null;
    if ($is_c_installed) {
      $unread_status = ConpherenceParticipationStatus::BEHIND;
      $unread = id(new ConpherenceParticipantCountQuery())
        ->withParticipantPHIDs(array($viewer->getPHID()))
        ->withParticipationStatus($unread_status)
        ->execute();
      $raw_message_count_number = idx($unread, $viewer->getPHID(), 0);
      $message_count_number = $this->formatNumber($raw_message_count_number);
    }
    $conpherence_data = array(
      'isInstalled' => $is_c_installed,
      'countType' => 'messages',
      'count' => $message_count_number,
      'rawCount' => $raw_message_count_number,
    );
    $this->setConpherenceData($conpherence_data);

    $notification_app = 'PhabricatorNotificationsApplication';
    $is_n_installed = PhabricatorApplication::isClassInstalledForViewer(
      $notification_app,
      $viewer);
    $notification_count_number = null;
    $raw_notification_count_number = null;
    if ($is_n_installed) {
      $raw_notification_count_number =
        id(new PhabricatorFeedStoryNotification())
        ->countUnread($viewer);
      $notification_count_number = $this->formatNumber(
        $raw_notification_count_number);
    }
    $notification_data = array(
      'isInstalled' => $is_n_installed,
      'countType' => 'notifications',
      'count' => $notification_count_number,
      'rawCount' => $raw_notification_count_number,
    );
    $this->setNotificationData($notification_data);

    return array(
      $notification_app => $this->getNotificationData(),
      $conpherence_app => $this->getConpherenceData(),
    );
  }

  private function formatNumber($number) {
    $formatted = $number;
    if ($number > 999) {
      $formatted = "\xE2\x88\x9E";
    }
    return $formatted;
  }

}
