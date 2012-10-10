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
final class ConduitAPI_differential_createcomment_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Add a comment to a Differential revision.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'required revisionid',
      'message'     => 'optional string',
      'action'      => 'optional string',
      'silent'      => 'optional bool',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_REVISION' => 'Bad revision ID.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $revision = id(new DifferentialRevision())->load(
      $request->getValue('revision_id'));
    if (!$revision) {
      throw new ConduitException('ERR_BAD_REVISION');
    }

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_CONDUIT,
      array());

    $action = $request->getValue('action');
    if (!$action) {
      $action = 'none';
    }

    $editor = new DifferentialCommentEditor(
      $revision,
      $action);
    $editor->setActor($request->getUser());
    $editor->setContentSource($content_source);
    $editor->setMessage($request->getValue('message'));
    $editor->setNoEmail($request->getValue('silent'));
    $editor->save();

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}
