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

final class PhabricatorProjectEditor {

  private $project;
  private $user;

  private $projectName;

  public function __construct(PhabricatorProject $project) {
    $this->project = $project;
  }

  public function setName($name) {
    $this->projectName = $name;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function save() {
    if (!$this->user) {
      throw new Exception('Call setUser() before save()!');
    }

    $project = $this->project;

    $is_new = !$project->getID();

    if ($is_new) {
      $project->setAuthorPHID($this->user->getPHID());
    }

    if (($this->projectName !== null) &&
        ($this->projectName !== $project->getName())) {
      $project->setName($this->projectName);
      $project->setPhrictionSlug($this->projectName);
      $this->validateName($project);
    }

    try {
      $project->save();
    } catch (AphrontQueryDuplicateKeyException $ex) {
      // We already validated the slug, but might race. Try again to see if
      // that's the issue. If it is, we'll throw a more specific exception. If
      // not, throw the original exception.
      $this->validateName($project);
      throw $ex;
    }

    // TODO: If we rename a project, we should move its Phriction page. Do
    // that once Phriction supports document moves.

    return $this;
  }

  private function validateName(PhabricatorProject $project) {
    $slug = $project->getPhrictionSlug();
    $name = $project->getName();

    if ($slug == '/') {
      throw new PhabricatorProjectNameCollisionException(
        "Project names must be unique and contain some letters or numbers.");
    }

    $id = $project->getID();
    $collision = id(new PhabricatorProject())->loadOneWhere(
      '(name = %s OR phrictionSlug = %s) AND id %Q %nd',
      $name,
      $slug,
      $id ? '!=' : 'IS NOT',
      $id ? $id : null);

    if ($collision) {
      $other_name = $collision->getName();
      $other_id = $collision->getID();
      throw new PhabricatorProjectNameCollisionException(
        "Project names must be unique. The name '{$name}' is too similar to ".
        "the name of another project, '{$other_name}' (Project ID: ".
        "{$other_id}). Choose a unique name.");
    }
  }

}
