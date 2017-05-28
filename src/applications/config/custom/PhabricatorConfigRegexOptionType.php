<?php

class PhabricatorConfigRegexOptionType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    foreach ($value as $pattern => $spec) {
      $ok = preg_match($pattern, '');
      if ($ok === false) {
        throw new Exception(
          pht(
            'The following regex is malformed and cannot be used: %s',
            $pattern));
      }
    }
  }

}
