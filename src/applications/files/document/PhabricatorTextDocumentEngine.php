<?php

abstract class PhabricatorTextDocumentEngine
  extends PhabricatorDocumentEngine {

  protected function canRenderDocumentType(PhabricatorDocumentRef $ref) {
    return $ref->isProbablyText();
  }

  protected function newTextDocumentContent($content) {
    $lines = phutil_split_lines($content);

    $view = id(new PhabricatorSourceCodeView())
      ->setHighlights($this->getHighlightedLines())
      ->setLines($lines);

    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-text',
      ),
      $view);

    return $container;
  }

  protected function loadTextData(PhabricatorDocumentRef $ref) {
    $content = $ref->loadData();
    $content = phutil_utf8ize($content);
    return $content;
  }

}
