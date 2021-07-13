<?php


class EmailRecipient {
  public string $email;
  public string $username;
  public int $timezoneOffset;
  public bool $isActor;

  public function __construct(string $email, string $username, DateTimeZone $timezone, bool $isActor) {
    $this->email = $email;
    $this->username = $username;
    $this->timezoneOffset = $timezone->getOffset(new DateTime(null, $timezone));
    $this->isActor = $isActor;
  }

  public static function from(PhabricatorUser $user, string $actorEmail): ?EmailRecipient {
    if ($user->getIsDisabled()) {
      // Don't send emails to disabled users
      return null;
    }

    $preferences = (new PhabricatorUserPreferencesQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withUserPHIDs([$user->getPHID()])
      ->needSyntheticPreferences(true)
      ->executeOne();

    $timezonePref = $preferences->getPreference('timezone');
    if ($timezonePref) {
      $timezone = new DateTimeZone($timezonePref);
    } else {
      $timezone = new DateTimeZone('UTC');
    }

    $mailPref = $preferences->getSettingValue(PhabricatorEmailNotificationsSetting::SETTINGKEY);
    if ($mailPref != PhabricatorEmailNotificationsSetting::VALUE_MOZILLA_MAIL) {
      // This user doesn't want the new emails, so don't consider them a recipient
      return null;
    }

    $username = $user->getUserName();
    $email = $user->loadPrimaryEmailAddress();
    return new EmailRecipient($email, $username, $timezone, $email == $actorEmail);
  }
}