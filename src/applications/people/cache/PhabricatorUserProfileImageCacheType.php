<?php

final class PhabricatorUserProfileImageCacheType
  extends PhabricatorUserCacheType {

  const CACHETYPE = 'user.profile';

  const KEY_URI = 'user.profile.image.uri.v1';

  public function getAutoloadKeys() {
    return array(
      self::KEY_URI,
    );
  }

  public function canManageKey($key) {
    return ($key === self::KEY_URI);
  }

  public function getDefaultValue() {
    return PhabricatorUser::getDefaultProfileImageURI();
  }

  public function newValueForUsers($key, array $users) {
    $viewer = $this->getViewer();

    $file_phids = mpull($users, 'getProfileImagePHID');
    $file_phids = array_filter($file_phids);

    if ($file_phids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs($file_phids)
        ->execute();
      $files = mpull($files, null, 'getPHID');
    } else {
      $files = array();
    }

    $results = array();
    foreach ($users as $user) {
      $image_phid = $user->getProfileImagePHID();
      if (isset($files[$image_phid])) {
        $image_uri = $files[$image_phid]->getBestURI();
      } else {
        $image_uri = PhabricatorUser::getDefaultProfileImageURI();
      }

      $user_phid = $user->getPHID();
      $version = $this->getCacheVersion($user);
      $results[$user_phid] = "{$version},{$image_uri}";
    }

    return $results;
  }

  public function getValueFromStorage($value) {
    $parts = explode(',', $value, 2);
    return end($parts);
  }

  public function shouldValidateRawCacheData() {
    return true;
  }

  public function isRawCacheDataValid(PhabricatorUser $user, $key, $data) {
    $parts = explode(',', $data, 2);
    $version = reset($parts);
    return ($version === $this->getCacheVersion($user));
  }

  private function getCacheVersion(PhabricatorUser $user) {
    $parts = array(
      PhabricatorEnv::getCDNURI('/'),
      PhabricatorEnv::getEnvConfig('cluster.instance'),
      $user->getProfileImagePHID(),
    );
    $parts = serialize($parts);
    return PhabricatorHash::digestForIndex($parts);
  }

}
