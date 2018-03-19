<?php

final class PhabricatorVoidDocumentEngine
  extends PhabricatorDocumentEngine {

  const ENGINEKEY = 'void';

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-file';
  }

  protected function getContentScore() {
    return 1000;
  }

  protected function canRenderDocumentType(PhabricatorDocumentRef $ref) {
    return true;
  }

  protected function newDocumentContent(PhabricatorDocumentRef $ref) {
    $message = pht(
      'No document engine can render the contents of this file.');

    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-message',
      ),
      $message);

    return $container;
  }

}
