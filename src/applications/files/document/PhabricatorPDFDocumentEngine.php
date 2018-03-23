<?php

final class PhabricatorPDFDocumentEngine
  extends PhabricatorDocumentEngine {

  const ENGINEKEY = 'pdf';

  public function getViewAsLabel(PhabricatorDocumentRef $ref) {
    return pht('View as PDF');
  }

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-file-pdf-o';
  }

  protected function canRenderDocumentType(PhabricatorDocumentRef $ref) {
    // Since we just render a link to the document anyway, we don't need to
    // check anything fancy in config to see if the MIME type is actually
    // viewable.

    return $ref->hasAnyMimeType(
      array(
        'application/pdf',
      ));
  }

  protected function newDocumentContent(PhabricatorDocumentRef $ref) {
    $viewer = $this->getViewer();

    $file = $ref->getFile();
    if ($file) {
      $source_uri = $file->getViewURI();
    } else {
      throw new PhutilMethodNotImplementedException();
    }

    $name = $ref->getName();
    $length = $ref->getByteLength();

    $link = id(new PhabricatorFileLinkView())
      ->setViewer($viewer)
      ->setFileName($name)
      ->setFileViewURI($source_uri)
      ->setFileViewable(true)
      ->setFileSize(phutil_format_bytes($length));

    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-pdf',
      ),
      $link);

    return $container;
  }

}
