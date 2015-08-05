<?php

final class PhamePostNewController extends PhameController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $id = $request->getURIData('id');

    $post = null;
    $view_uri = null;
    if ($id) {
      $post = id(new PhamePostQuery())
        ->setViewer($user)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$post) {
        return new Aphront404Response();
      }

      $view_uri = '/post/view/'.$post->getID().'/';
      $view_uri = $this->getApplicationURI($view_uri);

      if ($request->isFormPost()) {
        $blog = id(new PhameBlogQuery())
          ->setViewer($user)
          ->withIDs(array($request->getInt('blog')))
          ->requireCapabilities(
            array(
              PhabricatorPolicyCapability::CAN_JOIN,
            ))
          ->executeOne();

        if ($blog) {
          $post->setBlogPHID($blog->getPHID());
          $post->save();

          return id(new AphrontRedirectResponse())->setURI($view_uri);
        }
      }

      $title = pht('Move Post');
    } else {
      $title = pht('Create Post');
      $view_uri = $this->getApplicationURI('/post/new');
    }

    $blogs = id(new PhameBlogQuery())
      ->setViewer($user)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_JOIN,
        ))
      ->execute();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title, $view_uri);
    $display = array();
    $display[] = $crumbs;

    if (!$blogs) {
      $notification = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild(
          pht('You do not have permission to join any blogs. Create a blog '.
              'first, then you can post to it.'));

      $display[] = $notification;
    } else {
      $options = mpull($blogs, 'getName', 'getID');
      asort($options);

      $selected_value = null;
      if ($post && $post->getBlog()) {
        $selected_value = $post->getBlog()->getID();
      }

      $form = id(new AphrontFormView())
        ->setUser($user)
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Blog'))
            ->setName('blog')
            ->setOptions($options)
            ->setValue($selected_value));

      if ($post) {
        $form
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->setValue(pht('Move Post'))
              ->addCancelButton($view_uri));
      } else {
        $form
          ->setAction($this->getApplicationURI('post/edit/'))
          ->setMethod('GET')
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->setValue(pht('Continue')));
      }


      $form_box = id(new PHUIObjectBoxView())
        ->setHeaderText($title)
        ->setForm($form);

      $display[] = $form_box;
    }

    return $this->buildApplicationPage(
      $display,
      array(
        'title'   => $title,
      ));
  }

}
