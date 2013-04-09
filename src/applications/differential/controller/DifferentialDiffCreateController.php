<?php

final class DifferentialDiffCreateController extends DifferentialController {

  public function processRequest() {

    $request = $this->getRequest();

    if ($request->isFormPost()) {
      $diff = null;

      if ($request->getFileExists('diff-file')) {
        $diff = PhabricatorFile::readUploadedFileData($_FILES['diff-file']);
      } elseif ($request->getFileExists('whole-file')) {
        $file = PhabricatorFile::readUploadedFileData($_FILES['whole-file']);
        $context_lines = "-L '".$_FILES['whole-file']['name']." ".date("Y-m-d")."'";
        $context_lines = $context_lines." -L '".$_FILES['whole-file']['name']." ".date("Y-m-d")."'";
        $diff = ArcanistDiffUtils::renderDifferences("", $file, 3, $context_lines);
      } else {
        $diff = $request->getStr('diff');
      }

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
    $form
      ->setAction('/differential/diff/create/')
      ->setEncType('multipart/form-data')
      ->setUser($request->getUser())
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>',
        pht(
          'The best way to create a Differential diff is by using %s, but you '.
            'can also just paste a diff (e.g., from %s or %s) into this box '.
            'or upload it as a file if you really want.',
          $arcanist_link,
          phutil_tag('tt', array(), 'svn diff'),
          phutil_tag('tt', array(), 'git diff'))))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Raw Diff'))
          ->setName('diff')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel(pht('Raw Diff from file'))
          ->setName('diff-file'))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel(pht('Whole File'))
          ->setName('whole-file'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht("Create Diff \xC2\xBB")));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Create New Diff'));
    $panel->setNoBackground();
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Create Diff'))
        ->setHref('/differential/diff/create/'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $panel
      ),
      array(
        'title' => pht('Create Diff'),
        'device' => true,
      ));
  }

}
