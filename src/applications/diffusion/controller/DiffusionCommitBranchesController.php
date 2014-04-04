<?php

final class DiffusionCommitBranchesController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $data['user'] = $this->getRequest()->getUser();
    $this->diffusionRequest = DiffusionRequest::newFromDictionary($data);
  }

  public function processRequest() {
    $request = $this->getDiffusionRequest();

    $branches = array();
    try {
      $branches = $this->callConduitWithDiffusionRequest(
        'diffusion.branchquery',
        array(
          'contains' => $request->getCommit(),
        ));
    } catch (ConduitException $ex) {
      if ($ex->getMessage() != 'ERR-UNSUPPORTED-VCS') {
        throw $ex;
      }
    }

    $branches = DiffusionRepositoryRef::loadAllFromDictionaries($branches);

    $branch_links = array();
    foreach ($branches as $branch) {
      $branch_links[] = phutil_tag(
        'a',
        array(
          'href' => $request->generateURI(
            array(
              'action'  => 'browse',
              'branch'  => $branch->getShortName(),
            )),
        ),
        $branch->getShortName());
    }

    return id(new AphrontAjaxResponse())
      ->setContent($branch_links ? implode(', ', $branch_links) : 'None');
  }
}
