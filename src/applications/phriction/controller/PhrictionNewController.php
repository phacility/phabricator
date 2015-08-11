<?php

final class PhrictionNewController extends PhrictionController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $slug = PhabricatorSlug::normalize($request->getStr('slug'));

    if ($request->isFormPost()) {
      $document = id(new PhrictionDocumentQuery())
        ->setViewer($viewer)
        ->withSlugs(array($slug))
        ->executeOne();
      $prompt = $request->getStr('prompt', 'no');
      $document_exists = $document && $document->getStatus() ==
        PhrictionDocumentStatus::STATUS_EXISTS;

      if ($document_exists && $prompt == 'no') {
        $dialog = new AphrontDialogView();
        $dialog->setSubmitURI('/phriction/new/')
          ->setTitle(pht('Edit Existing Document?'))
          ->setUser($viewer)
          ->appendChild(pht(
            'The document %s already exists. Do you want to edit it instead?',
            phutil_tag('tt', array(), $slug)))
          ->addHiddenInput('slug', $slug)
          ->addHiddenInput('prompt', 'yes')
          ->addCancelButton('/w/')
          ->addSubmitButton(pht('Edit Document'));

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }

      $uri  = '/phriction/edit/?slug='.$slug;
      return id(new AphrontRedirectResponse())
        ->setURI($uri);
    }

    if ($slug == '/') {
      $slug = '';
    }

    $view = id(new PHUIFormLayoutView())
      ->appendChild(id(new AphrontFormTextControl())
                       ->setLabel('/w/')
                       ->setValue($slug)
                       ->setName('slug'));

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('New Document'))
      ->setSubmitURI('/phriction/new/')
      ->appendChild(phutil_tag('p',
        array(),
        pht('Create a new document at')))
      ->appendChild($view)
      ->addSubmitButton(pht('Create'))
      ->addCancelButton('/w/');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
