<?php

final class PhabricatorSetupCheckGD extends PhabricatorSetupCheck {

  protected function executeChecks() {
    if (!extension_loaded('gd')) {
      $message = pht(
        "The 'gd' extension is not installed. Without 'gd', support, ".
        "Phabricator will not be able to process or resize images ".
        "(for example, to generate thumbnails). Install or enable 'gd'.");

      $this->newIssue('extension.gd')
        ->setName(pht("Missing 'gd' Extension"))
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
          "The 'gd' extension has support for only some image types. ".
          "Phabricator will be unable to process images of the missing ".
          "types until you build 'gd' with support for them. ".
          "Supported types: %s. Missing types: %s.",
          $have,
          $missing);

        $this->newIssue('extension.gd.support')
          ->setName(pht("Partial 'gd' Support"))
          ->setMessage($message);
      }
    }
  }
}
