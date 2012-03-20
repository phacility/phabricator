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

final class PhabricatorRepositoryMercurialPullDaemon
  extends PhabricatorRepositoryPullLocalDaemon {

  protected function getSupportedRepositoryType() {
    return PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL;
  }

  protected function executeCreate(
    PhabricatorRepository $repository,
    $local_path) {
    $repository->execxRemoteCommand(
      'clone %s %s',
      $repository->getRemoteURI(),
      rtrim($local_path, '/'));
  }

  protected function executeUpdate(
    PhabricatorRepository $repository,
    $local_path) {

    // This is a local command, but needs credentials.
    $future = $repository->getRemoteCommandFuture('pull -u');
    $future->setCWD($local_path);

    try {
      $future->resolvex();
    } catch (CommandException $ex) {
      $err = $ex->getError();
      $stdout = $ex->getStdOut();

      // NOTE: Between versions 2.1 and 2.1.1, Mercurial changed the behavior
      // of "hg pull" to return 1 in case of a successful pull with no changes.
      // This behavior has been reverted, but users who updated between Feb 1,
      // 2012 and Mar 1, 2012 will have the erroring version. Do a dumb test
      // against stdout to check for this possibility.
      // See: https://github.com/facebook/phabricator/issues/101/

      // NOTE: Mercurial has translated versions, which translate this error
      // string. In a translated version, the string will be something else,
      // like "aucun changement trouve". There didn't seem to be an easy way
      // to handle this (there are hard ways but this is not a common problem
      // and only creates log spam, not application failures). Assume English.

      // TODO: Remove this once we're far enough in the future that deployment
      // of 2.1 is exceedingly rare?
      if ($err == 1 && preg_match('/no changes found/', $stdout)) {
        return;
      } else {
        throw $ex;
      }
    }

  }

}
