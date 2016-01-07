<?php

final class HeraldTranscriptDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'herald.transcripts';

  public function getExtensionName() {
    return pht('Herald Transcripts');
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $object_phid = $object->getPHID();

    $transcripts = id(new HeraldTranscript())->loadAllWhere(
      'objectPHID = %s',
      $object_phid);
    foreach ($transcripts as $transcript) {
      $engine->destroyObject($transcript);
    }
  }

}
