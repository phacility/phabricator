<?php

final class PhamePostMoveController extends PhamePostController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $post = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->executeOne();

    if (!$post) {
      return new Aphront404Response();
    }

    $view_uri = '/post/view/'.$post->getID().'/';
    $view_uri = $this->getApplicationURI($view_uri);

    if ($request->isFormPost()) {
      $blog = id(new PhameBlogQuery())
        ->setViewer($viewer)
        ->withIDs(array($request->getInt('blog')))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();

      if ($blog) {
        $post->setBlogPHID($blog->getPHID());
        $post->save();

        return id(new AphrontRedirectResponse())
          ->setURI($view_uri.'?moved=1');
      }
    }

    $blogs = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    $options = mpull($blogs, 'getName', 'getID');
    asort($options);

    $selected_value = null;
    if ($post && $post->getBlog()) {
      $selected_value = $post->getBlog()->getID();
    }

    $form = id(new PHUIFormLayoutView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Blog'))
          ->setName('blog')
          ->setOptions($options)
          ->setValue($selected_value));

    return $this->newDialog()
      ->setTitle(pht('Move Post'))
      ->appendChild($form)
      ->addSubmitButton(pht('Move Post'))
      ->addCancelButton($view_uri);

    }

}
