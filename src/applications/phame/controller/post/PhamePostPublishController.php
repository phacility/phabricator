<?php

final class PhamePostPublishController extends PhameController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $post = id(new PhamePostQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$post) {
      return new Aphront404Response();
    }

    $view_uri = $this->getApplicationURI('/post/view/'.$post->getID().'/');

    if ($request->isFormPost()) {
      $post->setVisibility(PhamePost::VISIBILITY_PUBLISHED);
      $post->setDatePublished(time());
      $post->save();

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Publish Post'))
          ->addCancelButton($view_uri));

    $frame = $this->renderPreviewFrame($post);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Preview Post'))
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Preview'), $view_uri);

    $nav = $this->renderSideNavFilterView(null);
    $nav->appendChild(
      array(
        $crumbs,
        $form_box,
        $frame,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => pht('Preview Post'),
      ));
  }

  private function renderPreviewFrame(PhamePost $post) {

    // TODO: Clean up this CSS.

    return phutil_tag(
      'div',
      array(
        'style' => 'text-align: center; padding: 1em;',
      ),
      phutil_tag(
        'iframe',
        array(
          'style' => 'width: 100%; height: 600px; '.
                     'border: 1px solid #303030;',
          'src' => $this->getApplicationURI('/post/framed/'.$post->getID().'/'),
        ),
        ''));
  }

}
