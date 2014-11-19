<?php

final class DifferentialDiffViewController extends DifferentialController {

  private $id;

  public function shouldAllowPublic() {
    return true;
  }

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

    $error_view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
    if ($diff->getRevisionID()) {
      $error_view->appendChild(
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
      $select[] = phutil_tag(
        'option',
        array('value' => ''),
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
            id(new PhutilUTF8StringTruncator())
            ->setMaximumGlyphs(128)
            ->truncateString(
              'D'.$revision->getID().' '.$revision->getTitle()));
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
        ->addHiddenInput(
          id(new DifferentialRepositoryField())->getFieldKey(),
          $diff->getRepositoryPHID())
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

        $error_view->appendChild($form);
    }

    $props = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d',
      $diff->getID());
    $props = mpull($props, 'getData', 'getName');

    $property_head = id(new PHUIHeaderView())
      ->setHeader(pht('Properties'));

    $property_view = new PHUIPropertyListView();

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
    $crumbs->addTextCrumb(pht('Diff %d', $diff->getID()));

    $prop_box = id(new PHUIObjectBoxView())
      ->setHeader($property_head)
      ->addPropertyList($property_view)
      ->setErrorView($error_view);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $prop_box,
        $table_of_contents,
        $details,
      ),
      array(
        'title' => pht('Diff View'),
      ));
  }

}
