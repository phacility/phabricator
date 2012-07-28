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

final class PhabricatorFactHomeController extends PhabricatorFactController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $types = array(
      '+N:*',
      '+N:DREV',
      'updated',
    );

    $engines = PhabricatorFactEngine::loadAllEngines();
    $specs = PhabricatorFactSpec::newSpecsForFactTypes($engines, $types);

    $facts = id(new PhabricatorFactAggregate())->loadAllWhere(
      'factType IN (%Ls)',
      $types);

    $rows = array();
    foreach ($facts as $fact) {
      $spec = $specs[$fact->getFactType()];

      $name = $spec->getName();
      $value = $spec->formatValueForDisplay($user, $fact->getValueX());

      $rows[] = array(
        phutil_escape_html($name),
        phutil_escape_html($value),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Fact',
        'Value',
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'n',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Facts!');
    $panel->appendChild($table);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Facts!',
      ));
  }

}
