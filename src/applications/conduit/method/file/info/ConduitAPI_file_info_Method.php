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
final class ConduitAPI_file_info_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Get information about a file.";
  }

  public function defineParamTypes() {
    return array(
      'phid' => 'optional phid',
      'id'   => 'optional id',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-NOT-FOUND'     => 'No such file exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $phid = $request->getValue('phid');
    $id   = $request->getValue('id');

    if ($id) {
      $file = id(new PhabricatorFile())->load($id);
    } else {
      $file = id(new PhabricatorFile())->loadOneWhere(
        'phid = %s',
        $phid);
    }

    if (!$file) {
      throw new ConduitException('ERR-NOT-FOUND');
    }

    $uri = $file->getBestURI();

    return array(
      'id'            => $file->getID(),
      'phid'          => $file->getPHID(),
      'objectName'    => 'F'.$file->getID(),
      'name'          => $file->getName(),
      'mimeType'      => $file->getMimeType(),
      'byteSize'      => $file->getByteSize(),
      'authorPHID'    => $file->getAuthorPHID(),
      'dateCreated'   => $file->getDateCreated(),
      'dateModified'  => $file->getDateModified(),
      'uri'           => PhabricatorEnv::getProductionURI($uri),
    );
  }

}
