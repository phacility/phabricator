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
final class ConduitAPI_paste_create_Method extends ConduitAPI_paste_Method {

  public function getMethodDescription() {
    return 'Create a new paste.';
  }

  public function defineParamTypes() {
    return array(
      'content'   => 'required string',
      'title'     => 'optional string',
      'language'  => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-NO-PASTE' => 'Paste may not be empty.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $content  = $request->getValue('content');
    $title    = $request->getValue('title');
    $language = $request->getValue('language');

    if (!strlen($content)) {
      throw new ConduitException('ERR-NO-PASTE');
    }

    $title = nonempty($title, 'Masterwork From Distant Lands');
    $language = nonempty($language, '');

    $user = $request->getUser();

    $paste_file = PhabricatorFile::newFromFileData(
      $content,
      array(
        'name'        => $title,
        'mime-type'   => 'text/plain; charset=utf-8',
        'authorPHID'  => $user->getPHID(),
      ));

    $paste = new PhabricatorPaste();
    $paste->setTitle($title);
    $paste->setLanguage($language);
    $paste->setFilePHID($paste_file->getPHID());
    $paste->setAuthorPHID($user->getPHID());
    $paste->save();

    return $this->buildPasteInfoDictionary($paste);
  }

}
