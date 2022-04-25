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

    $name = $ref->getName();
    if ($name !== null) {
      if (preg_match('/\.json\z/', $name)) {
        return 2000;
      }
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

      // See T13635. "phutil_json_decode()" always turns JSON into a PHP array,
      // and we lose the distinction between "{}" and "[]". This distinction is
      // important when rendering a document.
      $data = json_decode($raw_data, false);
      if (!$data) {
        throw new PhabricatorDocumentEngineParserException(
          pht(
            'Failed to "json_decode(...)" JSON document after successfully '.
            'decoding it with "phutil_json_decode(...).'));
      }

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
    } catch (PhabricatorDocumentEngineParserException $ex) {
      $message = $this->newMessage(
        pht(
          'Unable to parse this document as JSON: %s',
          $ex->getMessage()));

      $content = $raw_data;
    }

    return array(
      $message,
      $this->newTextDocumentContent($ref, $content),
    );
  }

}
