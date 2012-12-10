<?php

final class PhabricatorFeedStoryNotification extends PhabricatorFeedDAO {

  protected $userPHID;
  protected $primaryObjectPHID;
  protected $chronologicalKey;
  protected $hasViewed;

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS          => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS   => false,
    ) + parent::getConfiguration();
  }

  static public function updateObjectNotificationViews(
    PhabricatorUser $user,
    $object_phid) {

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $notification_table = new PhabricatorFeedStoryNotification();
    $conn = $notification_table->establishConnection('w');

    queryfx(
      $conn,
      "UPDATE %T
       SET hasViewed = 1
       WHERE userPHID = %s
         AND primaryObjectPHID = %s
         AND hasViewed = 0",
      $notification_table->getTableName(),
      $user->getPHID(),
      $object_phid);

    unset($unguarded);
  }

  public function countUnread(PhabricatorUser $user) {
    $conn = $this->establishConnection('r');

    $data = queryfx_one(
      $conn,
      'SELECT COUNT(*) as count
       FROM %T
       WHERE userPHID = %s AND hasViewed = 0',
      $this->getTableName(),
      $user->getPHID());

    return $data['count'];
  }

}
