<?php

/**
 * @group phame
 */
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

    $header = id(new PhabricatorHeaderView())
      ->setHeader('Preview Post');

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFlexible(true)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Publish Post'))
          ->addCancelButton($view_uri));

    $frame = $this->renderPreviewFrame($post);

    $nav = $this->renderSideNavFilterView(null);
    $nav->appendChild(
      array(
        $header,
        $form,
        $frame,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => pht('Preview Post'),
        'device'  => true,
      ));
  }

  private function renderPreviewFrame(PhamePost $post) {

    // TODO: Clean up this CSS.

    return phutil_render_tag(
      'div',
      array(
        'style' => 'text-align: center; padding: 1em;',
      ),
      phutil_render_tag(
        'iframe',
        array(
          'style' => 'width: 100%; height: 600px; '.
                     'border: 1px solid #303030; background: #303030;',
          'src' => $this->getApplicationURI('/post/framed/'.$post->getID().'/'),
        ),
        ''));
  }
}
