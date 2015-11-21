<?php

final class PhamePostNewController extends PhamePostController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $post = null;
    $view_uri = null;
    if ($id) {
      $post = id(new PhamePostQuery())
        ->setViewer($viewer)
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

          return id(new AphrontRedirectResponse())->setURI($view_uri);
        }
      }

      $title = pht('Move Post');
    } else {
      $title = pht('Create Post');
      $view_uri = $this->getApplicationURI('/post/new');
    }

    $blogs = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title, $view_uri);

    $notification = null;
    $form_box = null;
    if (!$blogs) {
      $notification = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild(
          pht('You do not have permission to post to any blogs. Create a blog '.
              'first, then you can post to it.'));

    } else {
      $options = mpull($blogs, 'getName', 'getID');
      asort($options);

      $selected_value = null;
      if ($post && $post->getBlog()) {
        $selected_value = $post->getBlog()->getID();
      }

      $form = id(new AphrontFormView())
        ->setUser($viewer)
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
    }

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $notification,
          $form_box,
      ));

    }

}
