<?php

final class PhamePostNewController extends PhamePostController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getInt('blog');

    if ($id) {
      $blog = id(new PhameBlogQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$blog) {
        return new Aphront404Response();
      }

      $view_uri = '/post/edit/?blog='.$blog->getID();
      $view_uri = $this->getApplicationURI($view_uri);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    $blogs = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    if (!$blogs) {
      $form = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild(
          pht('You do not have permission to post to any blogs. Create a blog '.
              'first, then you can post to it.'));

    } else {
      $options = mpull($blogs, 'getName', 'getID');
      asort($options);

      $form = id(new PHUIFormLayoutView())
        ->setUser($viewer)
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Blog'))
            ->setName('blog')
            ->setOptions($options));
    }

    return $this->newDialog()
      ->setTitle(pht('New Post'))
      ->appendChild($form)
      ->addSubmitButton(pht('Continue'));

    }

}
