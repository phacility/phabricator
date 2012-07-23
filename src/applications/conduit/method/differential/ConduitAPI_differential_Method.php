<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
