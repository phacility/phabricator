<?php

abstract class PhabricatorTextDocumentEngine
  extends PhabricatorDocumentEngine {

  private $encodingMessage = null;

  protected function canRenderDocumentType(PhabricatorDocumentRef $ref) {
    return $ref->isProbablyText();
  }

  public function canConfigureEncoding(PhabricatorDocumentRef $ref) {
    return true;
  }

  protected function newTextDocumentContent($content) {
    $lines = phutil_split_lines($content);

    $view = id(new PhabricatorSourceCodeView())
      ->setHighlights($this->getHighlightedLines())
      ->setLines($lines);

    $message = null;
    if ($this->encodingMessage !== null) {
      $message = $this->newMessage($this->encodingMessage);
    }

    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-text',
      ),
      array(
        $message,
        $view,
      ));

    return $container;
  }

  protected function loadTextData(PhabricatorDocumentRef $ref) {
    $content = $ref->loadData();

    $encoding = $this->getEncodingConfiguration();
    if ($encoding !== null) {
      if (function_exists('mb_convert_encoding')) {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        $this->encodingMessage = pht(
          'This document was converted from %s to UTF8 for display.',
          $encoding);
      } else {
        $this->encodingMessage = pht(
          'Unable to perform text encoding conversion: mbstring extension '.
          'is not available.');
      }
    } else {
      if (!phutil_is_utf8($content)) {
        if (function_exists('mb_detect_encoding')) {
          $try_encodings = array(
            'JIS' => pht('JIS'),
            'EUC-JP' => pht('EUC-JP'),
            'SJIS' => pht('Shift JIS'),
            'ISO-8859-1' => pht('ISO-8859-1 (Latin 1)'),
          );

          $guess = mb_detect_encoding($content, array_keys($try_encodings));
          if ($guess) {
            $content = mb_convert_encoding($content, 'UTF-8', $guess);
            $this->encodingMessage = pht(
              'This document is not UTF8. It was detected as %s and '.
              'converted to UTF8 for display.',
              idx($try_encodings, $guess, $guess));
          }
        }
      }
    }

    if (!phutil_is_utf8($content)) {
      $content = phutil_utf8ize($content);
      $this->encodingMessage = pht(
        'This document is not UTF8 and its text encoding could not be '.
        'detected automatically. Use "Change Text Encoding..." to choose '.
        'an encoding.');
    }

    return $content;
  }

}
