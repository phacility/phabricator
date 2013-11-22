<?php

final class DifferentialDiffCreateController extends DifferentialController {

  public function processRequest() {

    $request = $this->getRequest();

    $errors = array();
    $e_diff = null;
    $e_file = null;
    if ($request->isFormPost()) {
      $diff = null;

      if ($request->getFileExists('diff-file')) {
        $diff = PhabricatorFile::readUploadedFileData($_FILES['diff-file']);
      } else {
        $diff = $request->getStr('diff');
      }

      if (!strlen($diff)) {
        $errors[] = pht(
          "You can not create an empty diff. Copy/paste a diff, or upload a ".
          "diff file.");
        $e_diff = pht('Required');
        $e_file = pht('Required');
      }

      if (!$errors) {
        $call = new ConduitCall(
          'differential.createrawdiff',
          array(
            'diff' => $diff,
            ));
        $call->setUser($request->getUser());
        $result = $call->execute();

        $path = id(new PhutilURI($result['uri']))->getPath();
        return id(new AphrontRedirectResponse())->setURI($path);
      }
    }

    $form = new AphrontFormView();
    $arcanist_href = PhabricatorEnv::getDoclink(
      'article/Arcanist_User_Guide.html');
    $arcanist_link = phutil_tag(
      'a',
      array(
        'href' => $arcanist_href,
        'target' => '_blank',
      ),
      'Arcanist');

    $cancel_uri = $this->getApplicationURI();

    $form
      ->setAction('/differential/diff/create/')
      ->setEncType('multipart/form-data')
      ->setUser($request->getUser())
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
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setError($e_diff))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel(pht('Raw Diff From File'))
          ->setName('diff-file')
          ->setError($e_file))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue(pht("Create Diff")));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create New Diff'))
      ->setFormError($errors)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Create Diff')));

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setErrors($errors);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => pht('Create Diff'),
        'device' => true,
      ));
  }

}
