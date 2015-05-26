<?php

final class PhabricatorCustomHeaderConfigType
  extends PhabricatorConfigOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    if (phid_get_type($value) != PhabricatorFileFilePHIDType::TYPECONST) {
      throw new Exception(
        pht(
          '%s is not a valid file PHID.',
          $value));
    }

    $file = id(new PhabricatorFileQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($value))
      ->executeOne();
    if (!$file) {
      throw new Exception(
        pht(
          '%s is not a valid file PHID.',
          $value));
    }

    $most_open_policy = PhabricatorPolicies::getMostOpenPolicy();
    if ($file->getViewPolicy() != $most_open_policy) {
      throw new Exception(
        pht(
          'Specified file %s has policy "%s" but should have policy "%s".',
          $value,
          $file->getViewPolicy(),
          $most_open_policy));
    }

    if (!$file->isViewableImage()) {
      throw new Exception(
        pht(
          'Specified file %s is not a viewable image.',
          $value));
    }
  }

  public static function getExampleConfig() {
    $config = 'PHID-FILE-abcd1234abcd1234abcd';
    return $config;
  }

}
