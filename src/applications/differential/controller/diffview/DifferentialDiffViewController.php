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
    $request = $this->getRequest();

    $diff = id(new DifferentialDiff())->load($this->id);
    if (!$diff) {
      return new Aphront404Response();
    }

    $action_panel = new AphrontPanelView();
    $action_panel->setHeader('Preview Diff');
    $action_panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $action_panel->appendChild(
      '<p class="aphront-panel-instructions">Review the diff for correctness. '.
      'When you are satisfied, either <strong>create a new revision</strong> '.
      'or <strong>update an existing revision</strong>.');

    // TODO: implmenent optgroup support in AphrontFormSelectControl?
    $select = array();
    $select[] = '<optgroup label="Create New Revision">';
    $select[] = '<option value="">Create a new Revision...</option>';
    $select[] = '</optgroup>';

    $revision_data = new DifferentialRevisionListData(
      DifferentialRevisionListData::QUERY_OPEN_OWNED,
      array($request->getUser()->getPHID()));
    $revisions = $revision_data->loadRevisions();

    if ($revisions) {
      $select[] = '<optgroup label="Update Existing Revision">';
      foreach ($revisions as $revision) {
        $select[] = phutil_render_tag(
          'option',
          array(
            'value' => $revision->getID(),
          ),
          phutil_escape_html($revision->getTitle()));
      }
      $select[] = '</optgroup>';
    }

    $select =
      '<select name="revisionID">'.
        implode("\n", $select).
      '</select>';

    $action_form = new AphrontFormView();
    $action_form
      ->setUser($request->getUser())
      ->setAction('/differential/revision/edit/')
      ->addHiddenInput('diffID', $diff->getID())
      ->addHiddenInput('viaDiffView', 1)
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Attach To')
          ->setValue($select))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Continue'));

    $action_panel->appendChild($action_form);



    $changesets = $diff->loadChangesets();
    $changesets = msort($changesets, 'getSortKey');

    $table_of_contents = id(new DifferentialDiffTableOfContentsView())
      ->setChangesets($changesets);

    $details = id(new DifferentialChangesetListView())
      ->setChangesets($changesets);

    return $this->buildStandardPageResponse(
      '<div class="differential-primary-pane">'.
        implode(
          "\n",
          array(
            $action_panel->render(),
            $table_of_contents->render(),
            $details->render(),
          )).
      '</div>',
      array(
        'title' => 'Diff View',
      ));
  }

}
