<?php

final class PhabricatorSourceDocumentEngine
  extends PhabricatorTextDocumentEngine {

  const ENGINEKEY = 'source';

  public function getViewAsLabel(PhabricatorDocumentRef $ref) {
    return pht('View as Source');
  }

  public function canConfigureHighlighting(PhabricatorDocumentRef $ref) {
    return true;
  }

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-code';
  }

  protected function getContentScore(PhabricatorDocumentRef $ref) {
    return 1500;
  }

  protected function newDocumentContent(PhabricatorDocumentRef $ref) {
    $content = $this->loadTextData($ref);

    $highlighting = $this->getHighlightingConfiguration();
    if ($highlighting !== null) {
      $content = PhabricatorSyntaxHighlighter::highlightWithLanguage(
        $highlighting,
        $content);
    } else {
      $content = PhabricatorSyntaxHighlighter::highlightWithFilename(
        $ref->getName(),
        $content);
    }

    return $this->newTextDocumentContent($content);
  }

}
