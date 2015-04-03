<?php

final class DiffusionRepositoryEditBasicController
  extends DiffusionRepositoryEditController {

  protected function processDiffusionRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($user)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->needProjectPHIDs(true)
      ->withIDs(array($repository->getID()))
      ->executeOne();

    if (!$repository) {
      return new Aphront404Response();
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $v_name = $repository->getName();
    $v_desc = $repository->getDetail('description');
    $v_clone_name = $repository->getDetail('clone-name');
    $e_name = true;
    $errors = array();

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_desc = $request->getStr('description');
      $v_projects = $request->getArr('projectPHIDs');

      if ($repository->isHosted()) {
        $v_clone_name = $request->getStr('cloneName');
      }

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
        $type_clone_name = PhabricatorRepositoryTransaction::TYPE_CLONE_NAME;

        $xactions[] = id(clone $template)
          ->setTransactionType($type_name)
          ->setNewValue($v_name);

        $xactions[] = id(clone $template)
          ->setTransactionType($type_desc)
          ->setNewValue($v_desc);

        $xactions[] = id(clone $template)
          ->setTransactionType($type_clone_name)
          ->setNewValue($v_clone_name);

        $xactions[] = id(clone $template)
          ->setTransactionType($type_edge)
          ->setMetadataValue(
            'edge:type',
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
          ->setNewValue(
            array(
              '=' => array_fuse($v_projects),
            ));

        id(new PhabricatorRepositoryEditor())
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->setActor($user)
          ->applyTransactions($repository, $xactions);

        return id(new AphrontRedirectResponse())->setURI($edit_uri);
      }
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Basics'));

    $title = pht('Edit %s', $repository->getName());

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($v_name)
          ->setError($e_name));

    if ($repository->isHosted()) {
      $form
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setName('cloneName')
            ->setLabel(pht('Clone/Checkout As'))
            ->setValue($v_clone_name)
            ->setCaption(
              pht(
                'Optional directory name to use when cloning or checking out '.
                'this repository.')));
    }

    $form
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($user)
          ->setName('description')
          ->setLabel(pht('Description'))
          ->setValue($v_desc))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectDatasource())
          ->setName('projectPHIDs')
          ->setLabel(pht('Projects'))
          ->setValue($repository->getProjectPHIDs()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save'))
          ->addCancelButton($edit_uri))
      ->appendChild(id(new PHUIFormDividerControl()))
      ->appendRemarkupInstructions($this->getReadmeInstructions());

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form)
      ->setFormErrors($errors);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
      ));
  }

  private function getReadmeInstructions() {
    return pht(<<<EOTEXT
You can also create a `README` file at the repository root (or in any
subdirectory) to provide information about the repository. These formats are
supported:

| File Name       | Rendered As... |
|-----------------|----------------|
| `README`          | Plain Text |
| `README.txt`      | Plain Text |
| `README.remarkup` | Remarkup |
| `README.md`       | Remarkup |
| `README.rainbow`  | \xC2\xA1Fiesta! |
EOTEXT
);
  }

}
