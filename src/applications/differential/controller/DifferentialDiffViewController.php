<?php

final class DifferentialDiffViewController extends DifferentialController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$diff) {
      return new Aphront404Response();
    }

    if ($diff->getRevisionID()) {
      $top_part = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->appendChild(
          pht(
            'This diff belongs to revision %s.',
            phutil_tag(
              'a',
              array(
                'href' => '/D'.$diff->getRevisionID(),
              ),
              'D'.$diff->getRevisionID())));
    } else {
      // TODO: implement optgroup support in AphrontFormSelectControl?
      $select = array();
      $select[] = hsprintf('<optgroup label="%s">', pht('Create New Revision'));
      $select[] = hsprintf(
        '<option value="">%s</option>',
        pht('Create a new Revision...'));
      $select[] = hsprintf('</optgroup>');

      $revisions = id(new DifferentialRevisionQuery())
        ->setViewer($viewer)
        ->withAuthors(array($viewer->getPHID()))
        ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
        ->execute();

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
            'D'.$revision->getID().' '.$revision->getTitle());
        }
        $select[] = hsprintf('</optgroup>');
      }

      $select = phutil_tag(
        'select',
        array('name' => 'revisionID'),
        $select);

      $form = id(new AphrontFormView())
        ->setUser($request->getUser())
        ->setAction('/differential/revision/edit/')
        ->addHiddenInput('diffID', $diff->getID())
        ->addHiddenInput('viaDiffView', 1)
        ->setFlexible(true)
        ->appendRemarkupInstructions(
          pht(
            'Review the diff for correctness. When you are satisfied, either '.
            '**create a new revision** or **update an existing revision**.'))
        ->appendChild(
          id(new AphrontFormMarkupControl())
          ->setLabel(pht('Attach To'))
          ->setValue($select))
        ->appendChild(
          id(new AphrontFormSubmitControl())
          ->setValue(pht('Continue')));

      $top_part = $form;
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

    $property_head = id(new PhabricatorHeaderView())
      ->setHeader(pht('Properties'));

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

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Diff %d', $diff->getID())));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $top_part,
        $property_head,
        $property_view,
        $table_of_contents,
        $details,
      ),
      array(
        'title' => pht('Diff View'),
        'dust' => true,
      ));
  }

}
