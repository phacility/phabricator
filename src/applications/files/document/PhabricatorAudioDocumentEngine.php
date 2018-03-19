<?php

final class PhabricatorAudioDocumentEngine
  extends PhabricatorDocumentEngine {

  const ENGINEKEY = 'audio';

  public function getViewAsLabel(PhabricatorDocumentRef $ref) {
    return pht('View as Audio');
  }

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-file-sound-o';
  }

  protected function getByteLengthLimit() {
    return null;
  }

  protected function canRenderDocumentType(PhabricatorDocumentRef $ref) {
    $file = $ref->getFile();
    if ($file) {
      return $file->isAudio();
    }

    $viewable_types = PhabricatorEnv::getEnvConfig('files.viewable-mime-types');
    $viewable_types = array_keys($viewable_types);

    $audio_types = PhabricatorEnv::getEnvConfig('files.audio-mime-types');
    $audio_types = array_keys($audio_types);

    return
      $ref->hasAnyMimeType($viewable_types) &&
      $ref->hasAnyMimeType($audio_types);
  }

  protected function newDocumentContent(PhabricatorDocumentRef $ref) {
    $file = $ref->getFile();
    if ($file) {
      $source_uri = $file->getViewURI();
    } else {
      throw new PhutilMethodNotImplementedException();
    }

    $mime_type = $ref->getMimeType();

    $audio = phutil_tag(
      'audio',
      array(
        'controls' => 'controls',
      ),
      phutil_tag(
        'source',
        array(
          'src' => $source_uri,
          'type' => $mime_type,
        )));

    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-audio',
      ),
      $audio);

    return $container;
  }

}
