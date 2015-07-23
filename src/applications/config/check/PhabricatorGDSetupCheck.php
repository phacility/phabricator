<?php

final class PhabricatorGDSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    if (!extension_loaded('gd')) {
      $message = pht(
        "The '%s' extension is not installed. Without '%s', support, ".
        "Phabricator will not be able to process or resize images ".
        "(for example, to generate thumbnails). Install or enable '%s'.",
        'gd',
        'gd',
        'gd');

      $this->newIssue('extension.gd')
        ->setName(pht("Missing '%s' Extension", 'gd'))
        ->setMessage($message);
    } else {
      $image_type_map = array(
        'imagecreatefrompng'  => 'PNG',
        'imagecreatefromgif'  => 'GIF',
        'imagecreatefromjpeg' => 'JPEG',
      );

      $have = array();
      foreach ($image_type_map as $function => $image_type) {
        if (function_exists($function)) {
          $have[] = $image_type;
        }
      }

      $missing = array_diff($image_type_map, $have);
      if ($missing) {
        $missing = implode(', ', $missing);
        $have = implode(', ', $have);

        $message = pht(
          "The '%s' extension has support for only some image types. ".
          "Phabricator will be unable to process images of the missing ".
          "types until you build '%s' with support for them. ".
          "Supported types: %s. Missing types: %s.",
          'gd',
          'gd',
          $have,
          $missing);

        $this->newIssue('extension.gd.support')
          ->setName(pht("Partial '%s' Support", 'gd'))
          ->setMessage($message);
      }
    }
  }
}
