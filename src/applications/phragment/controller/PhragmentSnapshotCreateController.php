<?php

final class PhragmentSnapshotCreateController extends PhragmentController {

  private $dblob;

  public function willProcessRequest(array $data) {
    $this->dblob = idx($data, 'dblob', '');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $parents = $this->loadParentFragments($this->dblob);
    if ($parents === null) {
      return new Aphront404Response();
    }
    $fragment = nonempty(last($parents), null);
    if ($fragment === null) {
      return new Aphront404Response();
    }

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $fragment,
      PhabricatorPolicyCapability::CAN_EDIT);

    $children = id(new PhragmentFragmentQuery())
      ->setViewer($viewer)
      ->needLatestVersion(true)
      ->withLeadingPath($fragment->getPath().'/')
      ->execute();

    $errors = array();
    if ($request->isFormPost()) {

      $v_name = $request->getStr('name');
      if (strlen($v_name) === 0) {
        $errors[] = pht('You must specify a name.');
      }
      if (strpos($v_name, '/') !== false) {
        $errors[] = pht('Snapshot names can not contain "/".');
      }

      if (!count($errors)) {
        $snapshot = null;

        try {
          // Create the snapshot.
          $snapshot = id(new PhragmentSnapshot())
            ->setPrimaryFragmentPHID($fragment->getPHID())
            ->setName($v_name)
            ->save();
        } catch (AphrontDuplicateKeyQueryException $e) {
          $errors[] = pht('A snapshot with this name already exists.');
        }

        if (!count($errors)) {
          // Add the primary fragment.
          id(new PhragmentSnapshotChild())
            ->setSnapshotPHID($snapshot->getPHID())
            ->setFragmentPHID($fragment->getPHID())
            ->setFragmentVersionPHID($fragment->getLatestVersionPHID())
            ->save();

          // Add all of the child fragments.
          foreach ($children as $child) {
            id(new PhragmentSnapshotChild())
              ->setSnapshotPHID($snapshot->getPHID())
              ->setFragmentPHID($child->getPHID())
              ->setFragmentVersionPHID($child->getLatestVersionPHID())
              ->save();
          }

          return id(new AphrontRedirectResponse())
            ->setURI('/phragment/snapshot/view/'.$snapshot->getID());
        }
      }
    }

    $fragment_sequence = '-';
    if ($fragment->getLatestVersion() !== null) {
      $fragment_sequence = $fragment->getLatestVersion()->getSequence();
    }

    $rows = array();
    $rows[] = phutil_tag(
      'tr',
      array(),
      array(
        phutil_tag('th', array(), pht('Fragment')),
        phutil_tag('th', array(), pht('Version')),
      ));
    $rows[] = phutil_tag(
      'tr',
      array(),
      array(
        phutil_tag('td', array(), $fragment->getPath()),
        phutil_tag('td', array(), $fragment_sequence),
      ));
    foreach ($children as $child) {
      $sequence = '-';
      if ($child->getLatestVersion() !== null) {
        $sequence = $child->getLatestVersion()->getSequence();
      }
      $rows[] = phutil_tag(
        'tr',
        array(),
        array(
          phutil_tag('td', array(), $child->getPath()),
          phutil_tag('td', array(), $sequence),
        ));
    }

    $table = phutil_tag(
      'table',
      array('class' => 'remarkup-table'),
      $rows);

    $container = phutil_tag(
      'div',
      array('class' => 'phabricator-remarkup'),
      array(
        phutil_tag(
          'p',
          array(),
          pht(
            'The snapshot will contain the following fragments at '.
            'the specified versions: ')),
        $table,
      ));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Fragment Path'))
          ->setDisabled(true)
          ->setValue('/'.$fragment->getPath()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Snapshot Name'))
          ->setName('name'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Create Snapshot'))
          ->addCancelButton(
            $this->getApplicationURI('browse/'.$fragment->getPath())))
      ->appendChild(
        id(new PHUIFormDividerControl()))
      ->appendInstructions($container);

    $crumbs = $this->buildApplicationCrumbsWithPath($parents);
    $crumbs->addTextCrumb(pht('Create Snapshot'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create Snapshot of %s', $fragment->getName()))
      ->setFormErrors($errors)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $this->renderConfigurationWarningIfRequired(),
        $box,
      ),
      array(
        'title' => pht('Create Fragment'),
      ));
  }

}
