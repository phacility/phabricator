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
abstract class ConduitAPI_project_Method extends ConduitAPIMethod {

  protected function buildProjectInfoDictionary(PhabricatorProject $project) {
    $results = $this->buildProjectInfoDictionaries(array($project));
    return idx($results, $project->getPHID());
  }

  protected function buildProjectInfoDictionaries(array $projects) {
    if (!$projects) {
      return array();
    }

    $result = array();
    foreach ($projects as $project) {

      $member_phids = mpull($project->getAffiliations(), 'getUserPHID');
      $member_phids = array_values($member_phids);

      $result[$project->getPHID()] = array(
        'id'            => $project->getID(),
        'phid'          => $project->getPHID(),
        'name'          => $project->getName(),
        'members'       => $member_phids,
        'dateCreated'   => $project->getDateCreated(),
        'dateModified'  => $project->getDateModified(),
      );
    }

    return $result;
  }

}
