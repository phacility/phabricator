<?php

final class PhabricatorHexdumpDocumentEngine
  extends PhabricatorDocumentEngine {

  const ENGINEKEY = 'hexdump';

  public function getViewAsLabel(PhabricatorDocumentRef $ref) {
    return pht('View as Hexdump');
  }

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-microchip';
  }

  protected function getContentScore() {
    return 500;
  }

  protected function canRenderDocumentType(PhabricatorDocumentRef $ref) {
    return true;
  }

  protected function newDocumentContent(PhabricatorDocumentRef $ref) {
    $content = $ref->loadData();

    $output = array();
    $offset = 0;

    $lines = str_split($content, 16);
    foreach ($lines as $line) {
      $output[] = sprintf(
        '%08x  %- 23s  %- 23s  %- 16s',
        $offset,
        $this->renderHex(substr($line, 0, 8)),
        $this->renderHex(substr($line, 8)),
        $this->renderBytes($line));

      $offset += 16;
    }

    $output = implode("\n", $output);

    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-hexdump PhabricatorMonospaced',
      ),
      $output);

    return $container;
  }

  private function renderHex($bytes) {
    $length = strlen($bytes);

    $output = array();
    for ($ii = 0; $ii < $length; $ii++) {
      $output[] = sprintf('%02x', ord($bytes[$ii]));
    }

    return implode(' ', $output);
  }

  private function renderBytes($bytes) {
    $length = strlen($bytes);

    $output = array();
    for ($ii = 0; $ii < $length; $ii++) {
      $chr = $bytes[$ii];
      $ord = ord($chr);

      if ($ord < 0x20 || $ord >= 0x7F) {
        $chr = '.';
      }

      $output[] = $chr;
    }

    return implode('', $output);
  }

}
