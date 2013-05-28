<?php

final class DiffusionRepositoryEditBasicController extends DiffusionController {

  public function processRequest() {
    $request = $this->getRequest();
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
      ->withIDs(array($repository->getID()))
      ->executeOne();

    if (!$repository) {
      return new Aphront404Response();
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $v_name = $repository->getName();
    $v_desc = $repository->getDetail('description');
    $e_name = true;
    $errors = array();

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_desc = $request->getStr('description');

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

        $xactions[] = id(clone $template)
          ->setTransactionType($type_name)
          ->setNewValue($v_name);

        $xactions[] = id(clone $template)
          ->setTransactionType($type_desc)
          ->setNewValue($v_desc);

        id(new PhabricatorRepositoryEditor())
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->setActor($user)
          ->applyTransactions($repository, $xactions);

        return id(new AphrontRedirectResponse())->setURI($edit_uri);
      }
    }

    $content = array();

    $crumbs = $this->buildCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit Basics')));
    $content[] = $crumbs;

    $title = pht('Edit %s', $repository->getName());

    if ($errors) {
      $content[] = id(new AphrontErrorView())
        ->setTitle(pht('Form Errors'))
        ->setErrors($errors);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFlexible(true)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('description')
          ->setLabel(pht('Description'))
          ->setValue($v_desc))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save'))
          ->addCancelButton($edit_uri))
      ->appendChild(id(new PHUIFormDividerControl()))
      ->appendRemarkupInstructions($this->getReadmeInstructions());

    $content[] = $form;

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $title,
        'dust' => true,
        'device' => true,
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
