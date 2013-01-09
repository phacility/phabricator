<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_differential_Method extends ConduitAPIMethod {

  protected function buildDiffInfoDictionary(DifferentialDiff $diff) {
    $uri = '/differential/diff/'.$diff->getID().'/';
    $uri = PhabricatorEnv::getProductionURI($uri);

    return array(
      'id'  => $diff->getID(),
      'uri' => $uri,
    );
  }


  protected function buildInlineInfoDictionary(
    DifferentialInlineComment $inline,
    DifferentialChangeset $changeset = null) {

    $file_path = null;
    $diff_id = null;
    if ($changeset) {
      $file_path = $inline->getIsNewFile()
        ? $changeset->getFilename()
        : $changeset->getOldFile();

      $diff_id = $changeset->getDiffID();
    }

    return array(
      'id'          => $inline->getID(),
      'authorPHID'  => $inline->getAuthorPHID(),
      'filePath'    => $file_path,
      'isNewFile'   => $inline->getIsNewFile(),
      'lineNumber'  => $inline->getLineNumber(),
      'lineLength'  => $inline->getLineLength(),
      'diffID'      => $diff_id,
      'content'     => $inline->getContent(),
    );
  }


}
