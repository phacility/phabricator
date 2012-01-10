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
class ConduitAPI_differential_markcommitted_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Mark Differential revisions as committed.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'required revision_id',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => 'Revision was not found.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('revision_id');

    $revision = id(new DifferentialRevision())->load($id);
    if (!$revision) {
      throw new ConduitException('ERR_NOT_FOUND');
    }

    if ($revision->getStatus() ==
        ArcanistDifferentialRevisionStatus::COMMITTED) {
      // This can occur if someone runs 'mark-committed' and hits a race, or
      // they have a remote hook installed but don't have the
      // 'remote_hook_installed' flag set, or similar. In any case, just treat
      // it as a no-op rather than adding another "X committed this revision"
      // message to the revision comments.
      return;
    }

    $revision->loadRelationships();

    $editor = new DifferentialCommentEditor(
      $revision,
      $request->getUser()->getPHID(),
      DifferentialAction::ACTION_COMMIT);
    $editor->save();

    $revision->setStatus(ArcanistDifferentialRevisionStatus::COMMITTED);
    $revision->setDateCommitted(time());
    $revision->save();

    return;
  }

}
