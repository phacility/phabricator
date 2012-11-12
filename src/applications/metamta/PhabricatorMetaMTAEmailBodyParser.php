<?php

final class PhabricatorMetaMTAEmailBodyParser {

  public function stripTextBody($body) {
    return $this->stripSignature($this->stripQuotedText($body));
  }

  private function stripQuotedText($body) {
    $body = preg_replace(
      '/^\s*On\b.*\bwrote:.*?/msU',
      '',
      $body);

    // Outlook english
    $body = preg_replace(
      '/^\s*-----Original Message-----.*?/imsU',
      '',
      $body);

    // Outlook danish
    $body = preg_replace(
      '/^\s*-----Oprindelig Meddelelse-----.*?/imsU',
      '',
      $body);

    return rtrim($body);
  }

  private function stripSignature($body) {
    // Quasi-"standard" delimiter, for lols see:
    //   https://bugzilla.mozilla.org/show_bug.cgi?id=58406
    $body = preg_replace(
      '/^-- +$.*/sm',
      '',
      $body);

    // HTC Mail application (mobile)
    $body = preg_replace(
      '/^\s*^Sent from my HTC smartphone.*/sm',
      '',
      $body);

    // Apple iPhone
    $body = preg_replace(
      '/^\s*^Sent from my iPhone\s*$.*/sm',
      '',
      $body);

    return rtrim($body);
  }

}
