<?php

final class DiffusionRepositorySymbolsController
  extends DiffusionRepositoryEditController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $v_sources = $repository->getSymbolSources();
    $v_languages = $repository->getSymbolLanguages();
    if ($v_languages) {
      $v_languages = implode(', ', $v_languages);
    }
    $errors = array();

    if ($request->isFormPost()) {
      $v_sources = $request->getArr('sources');
      $v_languages = $request->getStrList('languages');
      $v_languages = array_map('phutil_utf8_strtolower', $v_languages);

      if (!$errors) {
        $xactions = array();
        $template = id(new PhabricatorRepositoryTransaction());

        $type_sources = PhabricatorRepositoryTransaction::TYPE_SYMBOLS_SOURCES;
        $type_lang = PhabricatorRepositoryTransaction::TYPE_SYMBOLS_LANGUAGE;

        $xactions[] = id(clone $template)
          ->setTransactionType($type_sources)
          ->setNewValue($v_sources);

        $xactions[] = id(clone $template)
          ->setTransactionType($type_lang)
          ->setNewValue($v_languages);

        try {
          id(new PhabricatorRepositoryEditor())
            ->setContinueOnNoEffect(true)
            ->setContentSourceFromRequest($request)
            ->setActor($viewer)
            ->applyTransactions($repository, $xactions);

          return id(new AphrontRedirectResponse())->setURI($edit_uri);
        } catch (Exception $ex) {
          $errors[] = $ex->getMessage();
        }
      }
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Symbols'));

    $title = pht('Edit Symbols (%s)', $repository->getName());
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-pencil');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions($this->getInstructions())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('languages')
          ->setLabel(pht('Indexed Languages'))
          ->setCaption(pht(
            'File extensions, separate with commas, for example: php, py. '.
            'Leave blank for "any".'))
          ->setValue($v_languages))

      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setName('sources')
          ->setLabel(pht('Uses Symbols From'))
          ->setDatasource(new DiffusionRepositoryDatasource())
          ->setValue($v_sources))

      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save'))
          ->addCancelButton($edit_uri));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Symbols'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form)
      ->setFormErrors($errors);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $form_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function getInstructions() {
    return pht(<<<EOT
Configure Symbols for this repository.

See [[%s | Symbol Indexes]] for more information on using symbols.
EOT
    ,
    PhabricatorEnv::getDoclink(
      'Diffusion User Guide: Symbol Indexes'));
  }

}
