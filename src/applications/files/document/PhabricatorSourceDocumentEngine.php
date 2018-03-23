<?php

final class PhabricatorSourceDocumentEngine
  extends PhabricatorTextDocumentEngine {

  const ENGINEKEY = 'source';

  public function getViewAsLabel(PhabricatorDocumentRef $ref) {
    return pht('View as Source');
  }

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-code';
  }

  protected function getContentScore(PhabricatorDocumentRef $ref) {
    return 1500;
  }

  protected function newDocumentContent(PhabricatorDocumentRef $ref) {
    $content = $this->loadTextData($ref);

    $content = PhabricatorSyntaxHighlighter::highlightWithFilename(
      $ref->getName(),
      $content);

    return $this->newTextDocumentContent($content);
  }

}
