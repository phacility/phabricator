<?php

final class PhabricatorPhoneNumber
  extends Phobject {

  private $number;

  public function __construct($raw_number) {
    $number = preg_replace('/[^\d]+/', '', $raw_number);

    if (!preg_match('/^[1-9]\d{1,14}\z/', $number)) {
      throw new Exception(
        pht(
          'Phone number ("%s") is not in a recognized format.',
          $raw_number));
    }

    $this->number = $number;
  }

  public function toE164() {
    return '+'.$this->number;
  }

}
