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

class DifferentialDiffViewController extends DifferentialController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $diff = id(new DifferentialDiff())->load($this->id);
    if (!$diff) {
      return new Aphront404Response();
    }

    $changesets = $diff->loadChangesets();
    $changesets = msort($changesets, 'getSortKey');

    $table_of_contents = id(new DifferentialDiffTableOfContentsView())
      ->setChangesets($changesets);

    $details = id(new DifferentialChangesetDetailView())
      ->setChangesets($changesets);

    return $this->buildStandardPageResponse(
      array(
        $table_of_contents,
        $details,
      ),
      array(
        'title' => 'Diff View',
      ));
  }

}
