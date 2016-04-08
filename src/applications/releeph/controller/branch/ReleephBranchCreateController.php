<?php

final class ReleephBranchCreateController extends ReleephProductController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('projectID');

    $product = id(new ReleephProductQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$product) {
      return new Aphront404Response();
    }
    $this->setProduct($product);


    $cut_point = $request->getStr('cutPoint');
    $symbolic_name = $request->getStr('symbolicName');

    if (!$cut_point) {
      $repository = $product->getRepository();
      switch ($repository->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $cut_point = $product->getTrunkBranch();
          break;
      }
    }

    $e_cut = true;
    $errors = array();

    $branch_date_control = id(new AphrontFormDateControl())
      ->setUser($request->getUser())
      ->setName('templateDate')
      ->setLabel(pht('Date'))
      ->setCaption(pht('The date used for filling out the branch template.'))
      ->setInitialTime(AphrontFormDateControl::TIME_START_OF_DAY);
    $branch_date = $branch_date_control->readValueFromRequest($request);

    if ($request->isFormPost()) {
      $cut_commit = null;
      if (!$cut_point) {
        $e_cut = pht('Required');
        $errors[] = pht('You must give a branch cut point');
      } else {
        try {
          $finder = id(new ReleephCommitFinder())
            ->setUser($request->getUser())
            ->setReleephProject($product);
          $cut_commit = $finder->fromPartial($cut_point);
        } catch (Exception $e) {
          $e_cut = pht('Invalid');
          $errors[] = $e->getMessage();
        }
      }

      if (!$errors) {
        $branch = id(new ReleephBranchEditor())
          ->setReleephProject($product)
          ->setActor($request->getUser())
          ->newBranchFromCommit(
            $cut_commit,
            $branch_date,
            $symbolic_name);

        $branch_uri = $this->getApplicationURI('branch/'.$branch->getID());

        return id(new AphrontRedirectResponse())
          ->setURI($branch_uri);
      }
    }

    $product_uri = $this->getProductViewURI($product);

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Symbolic Name'))
          ->setName('symbolicName')
          ->setValue($symbolic_name)
          ->setCaption(pht(
            'Mutable alternate name, for easy reference, (e.g. "LATEST")')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Cut point'))
          ->setName('cutPoint')
          ->setValue($cut_point)
          ->setError($e_cut)
          ->setCaption(pht(
            'A commit ID for your repo type, or a Diffusion ID like "rE123"')))
      ->appendChild($branch_date_control)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Cut Branch'))
          ->addCancelButton($product_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Branch'))
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($form);

    $title = pht('New Branch');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-plus-square');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }
}
