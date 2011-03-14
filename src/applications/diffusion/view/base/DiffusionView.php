<?php

/*
 * Copyright 2011 Facebook, Inc.
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

abstract class DiffusionView extends AphrontView {

  private $diffusionRequest;

  final public function setDiffusionRequest(DiffusionRequest $request) {
    $this->diffusionRequest = $request;
    return $this;
  }

  final public function getDiffusionRequest() {
    return $this->diffusionRequest;
  }

  final public function linkChange($change_type, $file_type) {

    $text = DifferentialChangeType::getFullNameForChangeType($change_type);
    if ($change_type == DifferentialChangeType::TYPE_CHILD) {
      // TODO: Don't link COPY_AWAY without a direct change.
      return $text;
    }
    if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
      return $text;
    }

    $drequest = $this->getDiffusionRequest();

    if ($drequest->getRawCommit()) {
      $commit = ';'.$drequest->getCommitURIComponent($drequest->getRawCommit());
    } else {
      $commit = null;
    }

    $repository = $drequest->getRepository();
    $callsign = $repository->getCallsign();

    $branch = $drequest->getBranchURIComponent($drequest->getBranch());
    $path = $branch.$drequest->getPath();

    return phutil_render_tag(
      'a',
      array(
        'href' => "/diffusion/{$callsign}/change/{$path}{$commit}",
      ),
      $text);
  }

  final public function linkHistory($path) {
    $drequest = $this->getDiffusionRequest();

    if ($drequest->getRawCommit()) {
      $commit = ';'.$drequest->getCommitURIComponent($drequest->getRawCommit());
    } else {
      $commit = null;
    }

    $repository = $drequest->getRepository();
    $callsign = $repository->getCallsign();

    $branch = $drequest->getBranchURIComponent($drequest->getBranch());
    $path = $branch.$path;

    $text = 'History';

    return phutil_render_tag(
      'a',
      array(
        'href' => "/diffusion/{$callsign}/history/{$path}{$commit}",
      ),
      $text);
  }

  final public function linkBrowse($path, array $details = array()) {
    $drequest = $this->getDiffusionRequest();

    $raw_commit = idx($details, 'commit', $drequest->getRawCommit());
    if ($raw_commit) {
      $commit = ';'.$drequest->getCommitURIComponent($raw_commit);
    } else {
      $commit = null;
    }

    $repository = $drequest->getRepository();
    $callsign = $repository->getCallsign();

    $branch = $drequest->getBranchURIComponent($drequest->getBranch());
    $path = $branch.$path;

    if (isset($details['text'])) {
      $text = phutil_escape_html($details['text']);
    } else {
      $text = 'Browse';
    }

    return phutil_render_tag(
      'a',
      array(
        'href' => "/diffusion/{$callsign}/browse/{$path}{$commit}",
      ),
      $text);
  }

  final public static function linkCommit($repository, $commit) {

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $commit_name = substr($commit, 0, 7);
        break;
      default:
        $commit_name = $commit;
        break;
    }

    $callsign = $repository->getCallsign();
    $commit_name = "r{$callsign}{$commit_name}";

    return phutil_render_tag(
      'a',
      array(
        'href' => "/r{$callsign}{$commit}",
      ),
      $commit_name);
  }

}
