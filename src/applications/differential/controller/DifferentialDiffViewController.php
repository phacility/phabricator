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
      $link = phutil_tag(
        'a',
        array(
          'href' => PhabricatorEnv::getURI('/D'.$diff->getRevisionID()),
        ),
        'D'.$diff->getRevisionID());
      $top_panel->appendChild(phutil_tag(
        'h1',
        array(),
        pht('This diff belongs to revision %s', $link)));
    } else {
      $action_panel = new AphrontPanelView();
      $action_panel->setHeader('Preview Diff');
      $action_panel->setWidth(AphrontPanelView::WIDTH_WIDE);
      $action_panel->appendChild(hsprintf(
        '<p class="aphront-panel-instructions">%s</p>',
        pht(
          'Review the diff for correctness. When you are satisfied, either '.
          '<strong>create a new revision</strong> or <strong>update '.
          'an existing revision</strong>.',
          hsprintf(''))));

      // TODO: implmenent optgroup support in AphrontFormSelectControl?
      $select = array();
      $select[] = hsprintf('<optgroup label="%s">', pht('Create New Revision'));
      $select[] = hsprintf(
        '<option value="">%s</option>',
        pht('Create a new Revision...'));
      $select[] = hsprintf('</optgroup>');

      $revision_data = new DifferentialRevisionListData(
        DifferentialRevisionListData::QUERY_OPEN_OWNED,
        array($request->getUser()->getPHID()));
      $revisions = $revision_data->loadRevisions();

      if ($revisions) {
        $select[] = hsprintf(
          '<optgroup label="%s">',
          pht('Update Existing Revision'));
        foreach ($revisions as $revision) {
          $select[] = phutil_tag(
            'option',
            array(
              'value' => $revision->getID(),
            ),
            $revision->getTitle());
        }
        $select[] = hsprintf('</optgroup>');
      }

      $select = phutil_tag(
        'select',
        array('name' => 'revisionID'),
        $select);

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

    $property_view = new PhabricatorPropertyListView();
    foreach ($dict as $key => $value) {
      $property_view->addProperty($key, $value);
    }

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
            $property_view,
            $table_of_contents->render(),
            $details->render(),
          )),
      array(
        'title' => pht('Diff View'),
      ));
  }

}
