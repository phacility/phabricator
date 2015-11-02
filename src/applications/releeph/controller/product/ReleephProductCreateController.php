<?php

final class ReleephProductCreateController extends ReleephProductController {

  public function handleRequest(AphrontRequest $request) {
    $name = trim($request->getStr('name'));
    $trunk_branch = trim($request->getStr('trunkBranch'));
    $repository_phid = $request->getStr('repositoryPHID');

    $e_name = true;
    $e_trunk_branch = true;
    $errors = array();

    if ($request->isFormPost()) {
      if (!$name) {
        $e_name = pht('Required');
        $errors[] = pht(
          'Your product should have a simple, descriptive name.');
      }

      if (!$trunk_branch) {
        $e_trunk_branch = pht('Required');
        $errors[] = pht(
          'You must specify which branch you will be picking from.');
      }

      $pr_repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($request->getUser())
        ->withPHIDs(array($repository_phid))
        ->executeOne();


      if (!$errors) {
        $releeph_product = id(new ReleephProject())
          ->setName($name)
          ->setTrunkBranch($trunk_branch)
          ->setRepositoryPHID($pr_repository->getPHID())
          ->setCreatedByUserPHID($request->getUser()->getPHID())
          ->setIsActive(1);

        try {
          $releeph_product->save();

          return id(new AphrontRedirectResponse())
            ->setURI($releeph_product->getURI());
        } catch (AphrontDuplicateKeyQueryException $ex) {
          $e_name = pht('Not Unique');
          $errors[] = pht('Another product already uses this name.');
        }
      }
    }

    $repo_options = $this->getRepositorySelectOptions();

    $product_name_input = id(new AphrontFormTextControl())
      ->setLabel(pht('Name'))
      ->setDisableAutocomplete(true)
      ->setName('name')
      ->setValue($name)
      ->setError($e_name)
      ->setCaption(pht('A name like "Thrift" but not "Thrift releases".'));

    $repository_input = id(new AphrontFormSelectControl())
      ->setLabel(pht('Repository'))
      ->setName('repositoryPHID')
      ->setValue($repository_phid)
      ->setOptions($repo_options);

    $branch_name_preview = id(new ReleephBranchPreviewView())
      ->setLabel(pht('Example Branch'))
      ->addControl('projectName', $product_name_input)
      ->addControl('repositoryPHID', $repository_input)
      ->addStatic('template', '')
      ->addStatic('isSymbolic', false);

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild($product_name_input)
      ->appendChild($repository_input)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Trunk'))
          ->setName('trunkBranch')
          ->setValue($trunk_branch)
          ->setError($e_trunk_branch)
          ->setCaption(pht(
            'The development branch, from which requests will be picked.')))
      ->appendChild($branch_name_preview)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/releeph/project/')
          ->setValue(pht('Create Release Product')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create New Product'))
      ->setFormErrors($errors)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('New Product'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => pht('Create New Product'),
      ));
  }

  private function getRepositorySelectOptions() {
    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getRequest()->getUser())
      ->execute();

    $repos = msort($repos, 'getName');
    $repos = mpull($repos, null, 'getID');

    $choices = array();

    foreach ($repos as $repo_id => $repo) {
      $repo_name = $repo->getName();
      $callsign = $repo->getCallsign();
      $choices[$repo->getPHID()] = "r{$callsign} ({$repo_name})";
    }

    ksort($choices);
    return $choices;
  }

}
