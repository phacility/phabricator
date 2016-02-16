<?php

final class DiffusionCommitBranchesController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $branch_limit = 10;
    $branches = DiffusionRepositoryRef::loadAllFromDictionaries(
      $this->callConduitWithDiffusionRequest(
        'diffusion.branchquery',
        array(
          'contains' => $drequest->getCommit(),
          'limit' => $branch_limit + 1,
        )));

    $has_more_branches = (count($branches) > $branch_limit);
    $branches = array_slice($branches, 0, $branch_limit);

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

    if ($has_more_branches) {
      $branch_links[] = phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action'  => 'branches',
            )),
        ),
        pht("More Branches\xE2\x80\xA6"));
    }

    return id(new AphrontAjaxResponse())
      ->setContent($branch_links ? implode(', ', $branch_links) : pht('None'));
  }
}
