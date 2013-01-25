<?php

final class DifferentialDiffViewController extends DifferentialController {

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

    if ($diff->getRevisionID()) {
      $top_panel = new AphrontPanelView();
      $top_panel->setWidth(AphrontPanelView::WIDTH_WIDE);
      $link = phutil_render_tag(
        'a',
        array(
          'href' => PhabricatorEnv::getURI('/D'.$diff->getRevisionID()),
        ),
        phutil_escape_html('D'.$diff->getRevisionID()));
      $top_panel->appendChild(
        "<h1>".pht('This diff belongs to revision %s', $link)."</h1>");
    } else {
      $action_panel = new AphrontPanelView();
      $action_panel->setHeader('Preview Diff');
      $action_panel->setWidth(AphrontPanelView::WIDTH_WIDE);
      $action_panel->appendChild(
        '<p class="aphront-panel-instructions">'.pht('Review the diff for '.
        'correctness. When you are satisfied, either <strong>create a new '.
        'revision</strong> or <strong>update an existing revision</strong>.'));

      // TODO: implmenent optgroup support in AphrontFormSelectControl?
      $select = array();
      $select[] = '<optgroup label="Create New Revision">';
      $select[] = '<option value="">'.
                    pht('Create a new Revision...').
                  '</option>';
      $select[] = '</optgroup>';

      $revision_data = new DifferentialRevisionListData(
        DifferentialRevisionListData::QUERY_OPEN_OWNED,
        array($request->getUser()->getPHID()));
      $revisions = $revision_data->loadRevisions();

      if ($revisions) {
        $select[] = '<optgroup label="'.pht('Update Existing Revision').'">';
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
          ->setLabel(pht('Attach To'))
          ->setValue($select))
        ->appendChild(
          id(new AphrontFormSubmitControl())
          ->setValue(pht('Continue')));

      $action_panel->appendChild($action_form);

      $top_panel = $action_panel;
    }

    $props = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d',
      $diff->getID());
    $props = mpull($props, 'getData', 'getName');

    $aux_fields = DifferentialFieldSelector::newSelector()
      ->getFieldSpecifications();
    foreach ($aux_fields as $key => $aux_field) {
      if (!$aux_field->shouldAppearOnDiffView()) {
        unset($aux_fields[$key]);
      } else {
        $aux_field->setUser($this->getRequest()->getUser());
      }
    }

    $dict = array();
    foreach ($aux_fields as $key => $aux_field) {
      $aux_field->setDiff($diff);
      $aux_field->setManualDiff($diff);
      $aux_field->setDiffProperties($props);
      $value = $aux_field->renderValueForDiffView();
      if (strlen($value)) {
        $label = rtrim($aux_field->renderLabelForDiffView(), ':');
        $dict[$label] = $value;
      }
    }

    $action_panel = new AphrontHeadsupView();
    $action_panel->setProperties($dict);
    $action_panel->setHeader(pht('Diff Properties'));

    $changesets = $diff->loadChangesets();
    $changesets = msort($changesets, 'getSortKey');

    $table_of_contents = id(new DifferentialDiffTableOfContentsView())
      ->setChangesets($changesets)
      ->setVisibleChangesets($changesets)
      ->setUnitTestData(idx($props, 'arc:unit', array()));

    $refs = array();
    foreach ($changesets as $changeset) {
      $refs[$changeset->getID()] = $changeset->getID();
    }

    $details = id(new DifferentialChangesetListView())
      ->setChangesets($changesets)
      ->setVisibleChangesets($changesets)
      ->setRenderingReferences($refs)
      ->setStandaloneURI('/differential/changeset/')
      ->setDiff($diff)
      ->setTitle(pht('Diff %d', $diff->getID()))
      ->setUser($request->getUser());

    return $this->buildStandardPageResponse(
      id(new DifferentialPrimaryPaneView())
        ->appendChild(
          array(
            $top_panel->render(),
            $action_panel->render(),
            $table_of_contents->render(),
            $details->render(),
          )),
      array(
        'title' => pht('Diff View'),
      ));
  }

}
