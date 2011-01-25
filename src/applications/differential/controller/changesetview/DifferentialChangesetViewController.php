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

class DifferentialChangesetViewController extends DifferentialController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $changeset = id(new DifferentialChangeset())->load($this->id);
    if (!$changeset) {
      return new Aphront404Response();
    }

    $changeset->attachHunks($changeset->loadHunks());

    $parser = new DifferentialChangesetParser();
    $parser->setChangeset($changeset);

    $output = $parser->render();

    $request = $this->getRequest();
    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())
        ->setContent($output);
    }

    $detail = new DifferentialChangesetDetailView();
    $detail->setChangeset($changeset);
    $detail->appendChild($output);

    // TODO: This is a bit of a hacky mess.
    $output =
      '<div style="padding: 2em 1em;">'.
        '<div class="differential-primary-pane">'.
          '<div class="differential-review-stage">'.
            $detail->render().
          '</div>'.
        '</div>'.
      '</div>';

    return $this->buildStandardPageResponse(
      array(
        $output
      ),
      array(
        'title' => 'Changeset View',
      ));
  }

}
