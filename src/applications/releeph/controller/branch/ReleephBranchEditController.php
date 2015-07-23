<?php

final class ReleephBranchEditController extends ReleephBranchController {

  private $branchID;

  public function willProcessRequest(array $data) {
    $this->branchID = $data['branchID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $branch = id(new ReleephBranchQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($this->branchID))
      ->executeOne();
    if (!$branch) {
      return new Aphront404Response();
    }
    $this->setBranch($branch);

    $symbolic_name = $request->getStr(
      'symbolicName',
      $branch->getSymbolicName());

    if ($request->isFormPost()) {
      $existing_with_same_symbolic_name =
        id(new ReleephBranch())
          ->loadOneWhere(
              'id != %d AND releephProjectID = %d AND symbolicName = %s',
              $branch->getID(),
              $branch->getReleephProjectID(),
              $symbolic_name);

      $branch->openTransaction();
      $branch
        ->setSymbolicName($symbolic_name);

      if ($existing_with_same_symbolic_name) {
        $existing_with_same_symbolic_name
          ->setSymbolicName(null)
          ->save();
      }

      $branch->save();
      $branch->saveTransaction();

      return id(new AphrontRedirectResponse())
        ->setURI($this->getBranchViewURI($branch));
    }

    $phids = array();

    $phids[] = $creator_phid = $branch->getCreatedByUserPHID();
    $phids[] = $cut_commit_phid = $branch->getCutPointCommitPHID();

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($request->getUser())
      ->withPHIDs($phids)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setLabel(pht('Branch Name'))
        ->setValue($branch->getName()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Cut Point'))
          ->setValue($handles[$cut_commit_phid]->renderLink()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Created By'))
          ->setValue($handles[$creator_phid]->renderLink()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Symbolic Name'))
          ->setName('symbolicName')
          ->setValue($symbolic_name)
          ->setCaption(pht(
            'Mutable alternate name, for easy reference, (e.g. "LATEST")')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($this->getBranchViewURI($branch))
          ->setValue(pht('Save Branch')));

    $title = pht(
      'Edit Branch %s',
      $branch->getDisplayNameWithDetail());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }
}
