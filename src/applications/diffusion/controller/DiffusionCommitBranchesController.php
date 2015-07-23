<?php

final class DiffusionCommitBranchesController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $branches = array();
        break;
      default:
        $branches = $this->callConduitWithDiffusionRequest(
          'diffusion.branchquery',
          array(
            'contains' => $drequest->getCommit(),
          ));
        break;
    }

    $branches = DiffusionRepositoryRef::loadAllFromDictionaries($branches);
    $branch_links = array();
    foreach ($branches as $branch) {
      $branch_links[] = phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action'  => 'browse',
              'branch'  => $branch->getShortName(),
            )),
        ),
        $branch->getShortName());
    }

    return id(new AphrontAjaxResponse())
      ->setContent($branch_links ? implode(', ', $branch_links) : pht('None'));
  }
}
