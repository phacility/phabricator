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

/**
 * This is a DAO for the Project -> subproject table, which
 * denormalizes the relationship between projects and subprojects into
 * a link table so it can be efficiently queried. This table is not
 * authoritative; the subprojectPHIDs field of PhabricatorProject is.
 * The rows in this table are regenerated when subprojects of a project
 * are updated.
 */
final class PhabricatorProjectSubproject extends PhabricatorProjectDAO {

  protected $projectPHID;
  protected $subprojectPHID;

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS          => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS   => false,
    );
  }

  public static function updateProjectSubproject(PhabricatorProject $project) {
    $dao = new PhabricatorProjectSubproject();
    $conn = $dao->establishConnection('w');

    $sql = array();
    foreach ($project->getSubprojectPHIDs() as $subproject_phid) {
      $sql[] = qsprintf(
        $conn,
        '(%s, %s)',
        $project->getPHID(),
        $subproject_phid);
    }

    queryfx(
      $conn,
      'DELETE FROM %T WHERE projectPHID = %s',
      $dao->getTableName(),
      $project->getPHID());
    if ($sql) {
      queryfx(
        $conn,
        'INSERT INTO %T (projectPHID, subprojectPHID) VALUES %Q',
        $dao->getTableName(),
        implode(', ', $sql));
    }
  }

}
