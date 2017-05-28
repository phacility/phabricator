<?php

final class PhabricatorUserBadgesCacheType
  extends PhabricatorUserCacheType {

  const CACHETYPE = 'badges.award';

  const KEY_BADGES = 'user.badge.award.v1';

  const BADGE_COUNT = 2;

  public function getAutoloadKeys() {
    return array(
      self::KEY_BADGES,
    );
  }

  public function canManageKey($key) {
    return ($key === self::KEY_BADGES);
  }

  public function getValueFromStorage($value) {
    return phutil_json_decode($value);
  }

  public function newValueForUsers($key, array $users) {
    if (!$users) {
      return array();
    }

    $user_phids = mpull($users, 'getPHID');

    $results = array();
    foreach ($user_phids as $user_phid) {
      $awards = id(new PhabricatorBadgesAwardQuery())
        ->setViewer($this->getViewer())
        ->withRecipientPHIDs(array($user_phid))
        ->withBadgeStatuses(array(PhabricatorBadgesBadge::STATUS_ACTIVE))
        ->setLimit(self::BADGE_COUNT)
        ->execute();

      $award_data = array();
      if ($awards) {
        foreach ($awards as $award) {
          $badge = $award->getBadge();
          $award_data[] = array(
            'icon' => $badge->getIcon(),
            'name' => $badge->getName(),
            'quality' => $badge->getQuality(),
            'id' => $badge->getID(),
          );
        }
      }
      $results[$user_phid] = phutil_json_encode($award_data);

    }

    return $results;
  }

}
