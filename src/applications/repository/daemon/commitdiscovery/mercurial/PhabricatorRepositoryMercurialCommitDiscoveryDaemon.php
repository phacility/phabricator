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

final class PhabricatorRepositoryMercurialCommitDiscoveryDaemon
  extends PhabricatorRepositoryCommitDiscoveryDaemon {

  protected function discoverCommits() {
    $repository = $this->getRepository();

    $vcs = $repository->getVersionControlSystem();
    if ($vcs != PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL) {
      throw new Exception("Repository is not a Mercurial repository.");
    }

    $repository_phid = $repository->getPHID();

    list($stdout) = $repository->execxLocalCommand('branches');

    $branches = ArcanistMercurialParser::parseMercurialBranches($stdout);
    $got_something = false;
    foreach ($branches as $name => $branch) {
      $commit = $branch['rev'];
      $commit = $this->getFullHash($commit);
      if ($this->isKnownCommit($commit)) {
        continue;
      } else {
        $this->discoverCommit($commit);
        $got_something = true;
      }
    }

    return $got_something;
  }

  private function getFullHash($commit) {

    // NOTE: Mercurial shortens hashes to 12 characters by default. This
    // implies collisions with as few as a few million commits. The
    // documentation sensibly advises "Do not use short-form IDs for
    // long-lived representations". It then continues "You can use the
    // --debug option to display the full changeset ID". What?! Yes, this
    // is in fact the only way to turn on full hashes, and the hg source
    // code is littered with "hexfn = ui.debugflag and hex or short" and
    // similar. There is no more-selective flag or config option.
    //
    // Unfortunately, "hg --debug" turns on tons of other extra output,
    // including full commit messages in "hg log" and "hg parents" (which
    // ignore --style); this renders them unparseable. So we have to use
    // "hg id" to convert short hashes into full hashes. See:
    //
    // <http://mercurial.selenic.com/wiki/ChangeSetID>
    //
    // Of course, this means that if there are collisions we will break here
    // (the short commit identifier won't be unambiguous) but maybe Mercurial
    // will have a --full-hashes flag or something by then and we can fix it
    // properly. Until we run into that, this allows us to store data in the
    // right format so when we eventually encounter this we won't have to
    // reparse every Mercurial repository.

    $repository = $this->getRepository();
    list($stdout) = $repository->execxLocalCommand(
      'id --debug -i --rev %s',
      $commit);
    return trim($stdout);
  }

  private function discoverCommit($commit) {
    $discover = array();
    $insert = array();

    $repository = $this->getRepository();

    $discover[] = $commit;
    $insert[] = $commit;

    $seen_parent = array();

    // For all the new commits at the branch heads, walk backward until we find
    // only commits we've aleady seen.
    while (true) {
      $target = array_pop($discover);
      list($stdout) = $repository->execxLocalCommand(
        'parents --style default --rev %s',
        $target);
      $parents = ArcanistMercurialParser::parseMercurialLog($stdout);
      if ($parents) {
        foreach ($parents as $parent) {
          $parent_commit = $parent['rev'];
          $parent_commit = $this->getFullHash($parent_commit);
          if (isset($seen_parent[$parent_commit])) {
            continue;
          }
          $seen_parent[$parent_commit] = true;
          if (!$this->isKnownCommit($parent_commit)) {
            $discover[] = $parent_commit;
            $insert[] = $parent_commit;
          }
        }
      }
      if (empty($discover)) {
        break;
      }
      $this->stillWorking();
    }

    while (true) {
      $target = array_pop($insert);
      list($stdout) = $repository->execxLocalCommand(
        'log --rev %s --template %s',
        $target,
        '{date|rfc822date}');
      $epoch = strtotime($stdout);

      $this->recordCommit($target, $epoch);

      if (empty($insert)) {
        break;
      }
    }
  }

}
