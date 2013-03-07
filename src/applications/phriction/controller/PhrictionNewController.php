<?php

/**
 * @group phriction
 */
final class PhrictionNewController extends PhrictionController {

  public function processRequest() {

    $request = $this->getRequest();
    $user    = $request->getUser();
    $slug    = PhabricatorSlug::normalize($request->getStr('slug'));

    if ($request->isFormPost()) {
      $uri  = '/phriction/edit/?slug='.$slug;
      return id(new AphrontRedirectResponse())
        ->setURI($uri);
    }

    if ($slug == '/') {
      $slug = '';
    }

    $view = id(new AphrontFormLayoutView())
      ->appendChild(id(new AphrontFormTextControl())
                       ->setLabel('/w/')
                       ->setValue($slug)
                       ->setName('slug'));

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
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
