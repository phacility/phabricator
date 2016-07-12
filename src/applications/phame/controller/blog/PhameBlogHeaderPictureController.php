<?php

final class PhameBlogHeaderPictureController
  extends PhameBlogController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $blog = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needHeaderImage(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    $blog_uri = '/phame/blog/manage/'.$id;

    $supported_formats = PhabricatorFile::getTransformableImageFormats();
    $e_file = true;
    $errors = array();
    $delete_header = ($request->getInt('delete') == 1);

    if ($request->isFormPost()) {
      if ($request->getFileExists('header')) {
        $file = PhabricatorFile::newFromPHPUpload(
          $_FILES['header'],
          array(
            'authorPHID' => $viewer->getPHID(),
            'canCDN' => true,
          ));
      } else if (!$delete_header) {
        $e_file = pht('Required');
        $errors[] = pht(
          'You must choose a file when uploading a new blog header.');
      }

      if (!$errors && !$delete_header) {
        if (!$file->isTransformableImage()) {
          $e_file = pht('Not Supported');
          $errors[] = pht(
            'This server only supports these image formats: %s.',
            implode(', ', $supported_formats));
        }
      }

      if (!$errors) {
        if ($delete_header) {
          $new_value = null;
        } else {
          $file->attachToObject($blog->getPHID());
          $new_value = $file->getPHID();
        }

        $xactions = array();
        $xactions[] = id(new PhameBlogTransaction())
          ->setTransactionType(PhameBlogTransaction::TYPE_HEADERIMAGE)
          ->setNewValue($new_value);

        $editor = id(new PhameBlogEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnMissingFields(true)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($blog, $xactions);

        return id(new AphrontRedirectResponse())->setURI($blog_uri);
      }
    }

    $title = pht('Edit Blog Header');

    $upload_form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setName('header')
          ->setLabel(pht('Upload Header'))
          ->setError($e_file)
          ->setCaption(
            pht('Supported formats: %s', implode(', ', $supported_formats))))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setName('delete')
          ->setLabel(pht('Delete Header'))
          ->addCheckbox(
            'delete',
            1,
            null,
            null))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($blog_uri)
          ->setValue(pht('Upload Header')));

    $upload_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Upload New Header'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($upload_form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Blogs'),
      $this->getApplicationURI('blog/'));
    $crumbs->addTextCrumb(
      $blog->getName(),
      $this->getApplicationURI('blog/view/'.$id));
    $crumbs->addTextCrumb(pht('Blog Header'));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Edit Blog Header'))
      ->setHeaderIcon('fa-camera');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $upload_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));

  }
}
