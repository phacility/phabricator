<?php

final class PhabricatorPhoneNumber
  extends Phobject {

  private $number;

  public function __construct($raw_number) {
    $number = preg_replace('/[^\d]+/', '', $raw_number);

    if (!preg_match('/^[1-9]\d{9,14}\z/', $number)) {
      throw new Exception(
        pht(
          'Phone number ("%s") is not in a recognized format: expected a '.
          'US number like "(555) 555-5555", or an international number '.
          'like "+55 5555 555555".',
          $raw_number));
    }

    // If the number didn't start with "+" and has has 10 digits, assume it is
    // a US number with no country code prefix, like "(555) 555-5555".
    if (!preg_match('/^[+]/', $raw_number)) {
      if (strlen($number) === 10) {
        $number = '1'.$number;
      }
    }

    $this->number = $number;
  }

  public function toE164() {
    return '+'.$this->number;
  }

}
