<?php

final class PhabricatorUserProfile extends PhabricatorUserDAO {

  protected $userPHID;
  protected $title;
  protected $blurb;
  protected $profileImagePHID;
  protected $icon;

  public static function initializeNewProfile(PhabricatorUser $user) {
    $default_icon = PhabricatorPeopleIconSet::getDefaultIconKey();

    return id(new self())
      ->setUserPHID($user->getPHID())
      ->setIcon($default_icon);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255',
        'blurb' => 'text',
        'profileImagePHID' => 'phid?',
        'icon' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'userPHID' => array(
          'columns' => array('userPHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getDisplayTitle() {
    $title = $this->getTitle();
    if (strlen($title)) {
      return $title;
    }

    $icon_key = $this->getIcon();
    return PhabricatorPeopleIconSet::getIconName($icon_key);
  }

}
