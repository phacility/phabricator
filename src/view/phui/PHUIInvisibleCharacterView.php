<?php

/**
 * API for replacing whitespace characters and some control characters with
 * their printable representations. This is useful for debugging and
 * displaying more helpful error messages to users.
 *
 */
final class PHUIInvisibleCharacterView extends AphrontView {

  private $inputText;
  private $plainText = false;

  // This is a list of the common invisible characters that are
  // actually typeable. Other invisible characters will simply
  // be displayed as their hex representations.
  private static $invisibleChars = array(
    "\x00" => 'NULL',
    "\t" => 'TAB',
    "\n" => 'NEWLINE',
    "\x20" => 'SPACE',
    );

  public function __construct($input_text) {
    $this->inputText = $input_text;
  }

  public function setPlainText($plain_text) {
    $this->plainText = $plain_text;
    return $this;
  }

  public function getStringParts() {
    $input_text = $this->inputText;
    $text_array = phutil_utf8v($input_text);

    for ($ii = 0; $ii < count($text_array); $ii++) {
      $char = $text_array[$ii];
      $char_hex = bin2hex($char);
      if (array_key_exists($char, self::$invisibleChars)) {
        $text_array[$ii] = array(
          'special' => true,
          'value' => '<'.self::$invisibleChars[$char].'>',
          );
      } else if (ord($char) < 32) {
        $text_array[$ii] = array(
          'special' => true,
          'value' => '<0x'.$char_hex.'>',
          );
      } else {
        $text_array[$ii] = array(
          'special' => false,
          'value' => $char,
          );
      }
    }
    return $text_array;
  }

  private function renderHtmlArray() {
    $html_array = array();
    $parts = $this->getStringParts();
    foreach ($parts as $part) {
      if ($part['special']) {
        $html_array[] = phutil_tag(
          'span',
          array('class' => 'invisible-special'),
          $part['value']);
      } else {
        $html_array[] = $part['value'];
      }
    }
    return $html_array;
  }

  private function renderPlainText() {
    $parts = $this->getStringParts();
    $res = '';
    foreach ($parts as $part) {
      $res .= $part['value'];
    }
    return $res;
  }

  public function render() {
    require_celerity_resource('phui-invisible-character-view-css');
    if ($this->plainText) {
      return $this->renderPlainText();
    } else {
      return $this->renderHtmlArray();
    }
  }

}
