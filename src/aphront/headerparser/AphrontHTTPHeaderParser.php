<?php

final class AphrontHTTPHeaderParser extends Phobject {

  private $name;
  private $content;
  private $pairs;

  public function parseRawHeader($raw_header) {
    $this->name = null;
    $this->content = null;

    $parts = explode(':', $raw_header, 2);
    $this->name = trim($parts[0]);
    if (count($parts) > 1) {
      $this->content = trim($parts[1]);
    }

    $this->pairs = null;

    return $this;
  }

  public function getHeaderName() {
    $this->requireParse();
    return $this->name;
  }

  public function getHeaderContent() {
    $this->requireParse();
    return $this->content;
  }

  public function getHeaderContentAsPairs() {
    $content = $this->getHeaderContent();


    $state = 'prekey';
    $length = strlen($content);

    $pair_name = null;
    $pair_value = null;

    $pairs = array();
    $ii = 0;
    while ($ii < $length) {
      $c = $content[$ii];

      switch ($state) {
        case 'prekey';
          // We're eating space in front of a key.
          if ($c == ' ') {
            $ii++;
            break;
          }
          $pair_name = '';
          $state = 'key';
          break;
        case 'key';
          // We're parsing a key name until we find "=" or ";".
          if ($c == ';') {
            $state = 'done';
            break;
          }

          if ($c == '=') {
            $ii++;
            $state = 'value';
            break;
          }

          $ii++;
          $pair_name .= $c;
          break;
        case 'value':
          // We found an "=", so now figure out if the value is quoted
          // or not.
          if ($c == '"') {
            $ii++;
            $state = 'quoted';
            break;
          }
          $state = 'unquoted';
          break;
        case 'quoted':
          // We're in a quoted string, parse until we find the closing quote.
          if ($c == '"') {
            $ii++;
            $state = 'done';
            break;
          }

          $ii++;
          $pair_value .= $c;
          break;
        case 'unquoted':
          // We're in an unquoted string, parse until we find a space or a
          // semicolon.
          if ($c == ' ' || $c == ';') {
            $state = 'done';
            break;
          }
          $ii++;
          $pair_value .= $c;
          break;
        case 'done':
          // We parsed something, so eat any trailing whitespace and semicolons
          // and look for a new value.
          if ($c == ' ' || $c == ';') {
            $ii++;
            break;
          }

          $pairs[] = array(
            $pair_name,
            $pair_value,
          );

          $pair_name = null;
          $pair_value = null;

          $state = 'prekey';
          break;
      }
    }

    if ($state == 'quoted') {
      throw new Exception(
        pht(
          'Header has unterminated double quote for key "%s".',
          $pair_name));
    }

    if ($pair_name !== null) {
      $pairs[] = array(
        $pair_name,
        $pair_value,
      );
    }

    return $pairs;
  }

  private function requireParse() {
    if ($this->name === null) {
      throw new PhutilInvalidStateException('parseRawHeader');
    }
  }

}
