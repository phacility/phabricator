<?php

final class DiffusionRepositoryEditBasicController
  extends DiffusionRepositoryEditController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $viewer = $request->getUser();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $v_name = $repository->getName();
    $v_desc = $repository->getDetail('description');
    $v_slug = $repository->getRepositorySlug();
    $v_callsign = $repository->getCallsign();
    $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $repository->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
    $e_name = true;
    $e_slug = null;
    $e_callsign = null;
    $errors = array();

    $validation_exception = null;
    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_desc = $request->getStr('description');
      $v_projects = $request->getArr('projectPHIDs');
      $v_slug = $request->getStr('slug');
      $v_callsign = $request->getStr('callsign');

      if (!strlen($v_name)) {
        $e_name = pht('Required');
        $errors[] = pht('Repository name is required.');
      } else {
        $e_name = null;
      }

      if (!$errors) {
        $xactions = array();
        $template = id(new PhabricatorRepositoryTransaction());

        $type_name = PhabricatorRepositoryTransaction::TYPE_NAME;
        $type_desc = PhabricatorRepositoryTransaction::TYPE_DESCRIPTION;
        $type_edge = PhabricatorTransactions::TYPE_EDGE;
        $type_slug = PhabricatorRepositoryTransaction::TYPE_SLUG;
        $type_callsign = PhabricatorRepositoryTransaction::TYPE_CALLSIGN;

        $xactions[] = id(clone $template)
          ->setTransactionType($type_name)
          ->setNewValue($v_name);

        $xactions[] = id(clone $template)
          ->setTransactionType($type_desc)
          ->setNewValue($v_desc);

        $xactions[] = id(clone $template)
          ->setTransactionType($type_slug)
          ->setNewValue($v_slug);

        $xactions[] = id(clone $template)
          ->setTransactionType($type_callsign)
          ->setNewValue($v_callsign);

        $xactions[] = id(clone $template)
          ->setTransactionType($type_edge)
          ->setMetadataValue(
            'edge:type',
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
          ->setNewValue(
            array(
              '=' => array_fuse($v_projects),
            ));

        $editor = id(new PhabricatorRepositoryEditor())
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->setActor($viewer);

        try {
          $editor->applyTransactions($repository, $xactions);

          // The preferred edit URI may have changed if the callsign or slug
          // were adjusted, so grab a fresh copy.
          $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

          return id(new AphrontRedirectResponse())->setURI($edit_uri);
        } catch (PhabricatorApplicationTransactionValidationException $ex) {
          $validation_exception = $ex;

          $e_slug = $ex->getShortMessage($type_slug);
          $e_callsign = $ex->getShortMessage($type_callsign);
        }
      }
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Basics'));

    $title = pht('Edit %s', $repository->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-pencil');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('slug')
          ->setLabel(pht('Short Name'))
          ->setValue($v_slug)
          ->setError($e_slug))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('callsign')
          ->setLabel(pht('Callsign'))
          ->setValue($v_callsign)
          ->setError($e_callsign))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setName('description')
          ->setLabel(pht('Description'))
          ->setValue($v_desc))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectDatasource())
          ->setName('projectPHIDs')
          ->setLabel(pht('Projects'))
          ->setValue($v_projects))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save'))
          ->addCancelButton($edit_uri))
      ->appendChild(id(new PHUIFormDividerControl()))
      ->appendRemarkupInstructions($this->getReadmeInstructions());

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Basic Information'))
      ->setValidationException($validation_exception)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form)
      ->setFormErrors($errors);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $form_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function getReadmeInstructions() {
    return pht(<<<EOTEXT
You can also create a `%s` file at the repository root (or in any
subdirectory) to provide information about the repository. These formats are
supported:

| File Name | Rendered As...  |
|-----------|-----------------|
| `%s`      | Plain Text      |
| `%s`      | Plain Text      |
| `%s`      | Remarkup        |
| `%s`      | Remarkup        |
| `%s`      | \xC2\xA1Fiesta! |
EOTEXT
  ,
  'README',
  'README',
  'README.txt',
  'README.remarkup',
  'README.md',
  'README.rainbow');
  }

}
