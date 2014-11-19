<?php

final class DifferentialDiffCreateController extends DifferentialController {

  public function processRequest() {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $diff = null;
    // This object is just for policy stuff
    $diff_object = DifferentialDiff::initializeNewDiff($viewer);
    $repository_phid = null;
    $repository_value = array();
    $errors = array();
    $e_diff = null;
    $e_file = null;
    $validation_exception = null;
    if ($request->isFormPost()) {

      $repository_tokenizer = $request->getArr(
        id(new DifferentialRepositoryField())->getFieldKey());
      if ($repository_tokenizer) {
        $repository_phid = reset($repository_tokenizer);
      }

      if ($request->getFileExists('diff-file')) {
        $diff = PhabricatorFile::readUploadedFileData($_FILES['diff-file']);
      } else {
        $diff = $request->getStr('diff');
      }

      if (!strlen($diff)) {
        $errors[] = pht(
          'You can not create an empty diff. Copy/paste a diff, or upload a '.
          'diff file.');
        $e_diff = pht('Required');
        $e_file = pht('Required');
      }

      if (!$errors) {
        try {
          $call = new ConduitCall(
            'differential.createrawdiff',
            array(
              'diff' => $diff,
              'repositoryPHID' => $repository_phid,
              'viewPolicy' => $request->getStr('viewPolicy'),));
          $call->setUser($viewer);
          $result = $call->execute();
          $path = id(new PhutilURI($result['uri']))->getPath();
          return id(new AphrontRedirectResponse())->setURI($path);
        } catch (PhabricatorApplicationTransactionValidationException $ex) {
          $validation_exception = $ex;
        }
      }
    }

    $form = new AphrontFormView();
    $arcanist_href = PhabricatorEnv::getDoclink('Arcanist User Guide');
    $arcanist_link = phutil_tag(
      'a',
      array(
        'href' => $arcanist_href,
        'target' => '_blank',
      ),
      'Arcanist');

    $cancel_uri = $this->getApplicationURI();

    if ($repository_phid) {
      $repository_value = $this->loadViewerHandles(array($repository_phid));
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($diff_object)
      ->execute();

    $form
      ->setAction('/differential/diff/create/')
      ->setEncType('multipart/form-data')
      ->setUser($viewer)
      ->appendInstructions(
        pht(
          'The best way to create a Differential diff is by using %s, but you '.
          'can also just paste a diff (for example, from %s, %s or %s) into '.
          'this box, or upload a diff file.',
          $arcanist_link,
          phutil_tag('tt', array(), 'svn diff'),
          phutil_tag('tt', array(), 'git diff'),
          phutil_tag('tt', array(), 'hg diff --git')))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Raw Diff'))
          ->setName('diff')
          ->setValue($diff)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setError($e_diff))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel(pht('Raw Diff From File'))
          ->setName('diff-file')
          ->setError($e_file))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
        ->setName(id(new DifferentialRepositoryField())->getFieldKey())
        ->setLabel(pht('Repository'))
        ->setDatasource(new DiffusionRepositoryDatasource())
        ->setValue($repository_value)
        ->setLimit(1))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setUser($viewer)
        ->setName('viewPolicy')
        ->setPolicyObject($diff_object)
        ->setPolicies($policies)
        ->setCapability(PhabricatorPolicyCapability::CAN_VIEW))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue(pht('Create Diff')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create New Diff'))
      ->setValidationException($validation_exception)
      ->setForm($form)
      ->setFormErrors($errors);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Create Diff'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => pht('Create Diff'),
      ));
  }

}
