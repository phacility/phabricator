<?php

final class PhabricatorJSONDocumentEngine
  extends PhabricatorTextDocumentEngine {

  const ENGINEKEY = 'json';

  public function getViewAsLabel(PhabricatorDocumentRef $ref) {
    return pht('View as JSON');
  }

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-database';
  }

  protected function getContentScore(PhabricatorDocumentRef $ref) {
    if (preg_match('/\.json\z/', $ref->getName())) {
      return 2000;
    }

    if ($ref->isProbablyJSON()) {
      return 1750;
    }

    return 500;
  }

  protected function newDocumentContent(PhabricatorDocumentRef $ref) {
    $raw_data = $this->loadTextData($ref);

    try {
      $data = phutil_json_decode($raw_data);

      if (preg_match('/^\s*\[/', $raw_data)) {
        $content = id(new PhutilJSON())->encodeAsList($data);
      } else {
        $content = id(new PhutilJSON())->encodeFormatted($data);
      }

      $message = null;
      $content = PhabricatorSyntaxHighlighter::highlightWithLanguage(
        'json',
        $content);
    } catch (PhutilJSONParserException $ex) {
      $message = $this->newMessage(
        pht(
          'This document is not valid JSON: %s',
          $ex->getMessage()));

      $content = $raw_data;
    }

    return array(
      $message,
      $this->newTextDocumentContent($content),
    );
  }

}
