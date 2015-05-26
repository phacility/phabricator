<?php

final class PholioInlineController extends PholioController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($this->id) {
      $inline = id(new PholioTransactionComment())->load($this->id);

      if (!$inline) {
        return new Aphront404Response();
      }

      if ($inline->getTransactionPHID()) {
        $mode = 'view';
      } else {
        if ($inline->getAuthorPHID() == $viewer->getPHID()) {
          $mode = 'edit';
        } else {
          return new Aphront404Response();
        }
      }
    } else {
      $mock = id(new PholioMockQuery())
        ->setViewer($viewer)
        ->withIDs(array($request->getInt('mockID')))
        ->executeOne();

      if (!$mock) {
        return new Aphront404Response();
      }

      $inline = id(new PholioTransactionComment())
        ->setImageID($request->getInt('imageID'))
        ->setX($request->getInt('startX'))
        ->setY($request->getInt('startY'))
        ->setCommentVersion(1)
        ->setAuthorPHID($viewer->getPHID())
        ->setEditPolicy($viewer->getPHID())
        ->setViewPolicy(PhabricatorPolicies::POLICY_PUBLIC)
        ->setContentSourceFromRequest($request)
        ->setWidth($request->getInt('endX') - $request->getInt('startX'))
        ->setHeight($request->getInt('endY') - $request->getInt('startY'));

      $mode = 'new';
    }

    $v_content = $inline->getContent();

    // TODO: Not correct, but we don't always have a mock right now.
    $mock_uri = '/';

    if ($mode == 'view') {
      require_celerity_resource('pholio-inline-comments-css');
      $image = id(new PholioImageQuery())
        ->setViewer($viewer)
        ->withIDs(array($inline->getImageID()))
        ->executeOne();

      $handles = $this->loadViewerHandles(array($inline->getAuthorPHID()));
      $author_handle = $handles[$inline->getAuthorPHID()];

      $file = $image->getFile();
      if (!$file->isViewableImage()) {
        throw new Exception(pht('File is not viewable.'));
      }

      $image_uri = $file->getBestURI();

      $thumb = id(new PHUIImageMaskView())
        ->addClass('mrl')
        ->setImage($image_uri)
        ->setDisplayHeight(200)
        ->setDisplayWidth(498)
        ->withMask(true)
        ->centerViewOnPoint(
          $inline->getX(), $inline->getY(),
          $inline->getHeight(), $inline->getWidth());

      $comment_head = phutil_tag(
        'div',
        array(
          'class' => 'pholio-inline-comment-head',
        ),
        $author_handle->renderLink());

      $comment_body = phutil_tag(
        'div',
        array(
          'class' => 'pholio-inline-comment-body',
        ),
        PhabricatorMarkupEngine::renderOneObject(
            id(new PhabricatorMarkupOneOff())
              ->setContent($inline->getContent()),
            'default',
            $viewer));

      return $this->newDialog()
        ->setTitle(pht('Inline Comment'))
        ->appendChild($thumb)
        ->appendChild($comment_head)
        ->appendChild($comment_body)
        ->addCancelButton($mock_uri, pht('Close'));
    }

    if ($request->isFormPost()) {
      $v_content = $request->getStr('content');

      if (strlen($v_content)) {
        $inline->setContent($v_content);
        $inline->save();
        $dictionary = $inline->toDictionary();
      } else if ($inline->getID()) {
        $inline->delete();
        $dictionary = array();
      }

      return id(new AphrontAjaxResponse())->setContent($dictionary);
    }

    switch ($mode) {
      case 'edit':
        $title = pht('Edit Inline Comment');
        $submit_text = pht('Save Draft');
        break;
      case 'new':
        $title = pht('New Inline Comment');
        $submit_text = pht('Save Draft');
        break;
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    if ($mode == 'new') {
      $params = array(
        'mockID' => $request->getInt('mockID'),
        'imageID' => $request->getInt('imageID'),
        'startX' => $request->getInt('startX'),
        'startY' => $request->getInt('startY'),
        'endX' => $request->getInt('endX'),
        'endY' => $request->getInt('endY'),
      );
      foreach ($params as $key => $value) {
        $form->addHiddenInput($key, $value);
      }
    }

    $form
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setName('content')
          ->setLabel(pht('Comment'))
          ->setValue($v_content));

    return $this->newDialog()
      ->setTitle($title)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($mock_uri)
      ->addSubmitButton($submit_text);
  }

}
