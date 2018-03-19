<?php

final class PhabricatorImageDocumentEngine
  extends PhabricatorDocumentEngine {

  const ENGINEKEY = 'image';

  public function getViewAsLabel(PhabricatorDocumentRef $ref) {
    return pht('View as Image');
  }

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-file-image-o';
  }

  protected function getByteLengthLimit() {
    return (1024 * 1024 * 64);
  }

  protected function canRenderDocumentType(PhabricatorDocumentRef $ref) {
    $file = $ref->getFile();
    if ($file) {
      return $file->isViewableImage();
    }

    $viewable_types = PhabricatorEnv::getEnvConfig('files.viewable-mime-types');
    $viewable_types = array_keys($viewable_types);

    $image_types = PhabricatorEnv::getEnvConfig('files.image-mime-types');
    $image_types = array_keys($image_types);

    return
      $ref->hasAnyMimeType($viewable_types) &&
      $ref->hasAnyMimeType($image_types);
  }

  protected function newDocumentContent(PhabricatorDocumentRef $ref) {
    $file = $ref->getFile();
    if ($file) {
      $source_uri = $file->getViewURI();
    } else {
      // We could use a "data:" URI here. It's not yet clear if or when we'll
      // have a ref but no backing file.
      throw new PhutilMethodNotImplementedException();
    }

    $image = phutil_tag(
      'img',
      array(
        'src' => $source_uri,
      ));

    $linked_image = phutil_tag(
      'a',
      array(
        'href' => $source_uri,
        'rel' => 'noreferrer',
      ),
      $image);

    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-image',
      ),
      $linked_image);

    return $container;
  }

}
