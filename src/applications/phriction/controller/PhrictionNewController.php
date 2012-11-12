<?php

/**
 * @group phriction
 */
final class PhrictionNewController extends PhrictionController {

  public function processRequest() {

    $request = $this->getRequest();
    $user    = $request->getUser();

    if ($request->isFormPost()) {
      $slug = PhabricatorSlug::normalize($request->getStr('slug'));
      $uri  = '/phriction/edit/?slug='.$slug;
      return id(new AphrontRedirectResponse())
        ->setURI($uri);
    }

    $view = id(new AphrontFormLayoutView())
      ->appendChild(id(new AphrontFormTextControl())
                       ->setLabel('/w/')
                       ->setName('slug'));

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('New Document'))
      ->appendChild(phutil_render_tag('p',
        array(),
      pht('Create a new document at')))
      ->appendChild($view)
      ->addSubmitButton(pht('Create'))
      ->addCancelButton($request->getRequestURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
