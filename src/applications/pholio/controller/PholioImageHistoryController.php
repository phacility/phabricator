<?php

/**
 * @group pholio
 */
final class PholioImageHistoryController extends PholioController {

  private $imageID;

  public function willProcessRequest(array $data) {
    $this->imageID = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $image = id(new PholioImageQuery())
      ->setViewer($user)
      ->withIDs(array($this->imageID))
      ->executeOne();

    if (!$image) {
      return new Aphront404Response();
    }

    // note while we have a mock object, its missing images we need to show
    // the history of what's happened here.
    // fetch the real deal

    $mock = id(new PholioMockQuery())
      ->setViewer($user)
      ->needImages(true)
      ->withIDs(array($image->getMockID()))
      ->executeOne();

    $phids = array($mock->getAuthorPHID());
    $this->loadHandles($phids);

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    $engine->addObject($mock, PholioMock::MARKUP_FIELD_DESCRIPTION);
    $engine->process();


    $images = $mock->getImageHistorySet($this->imageID);
    $mock->attachImages($images);
    $latest_image = last($images);

    $title = pht(
      'Image history for "%s" from the mock "%s."',
      $latest_image->getName(),
      $mock->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    require_celerity_resource('pholio-css');
    require_celerity_resource('pholio-inline-comments-css');

    $comment_form_id = null;
    $output = id(new PholioMockImagesView())
      ->setRequestURI($request->getRequestURI())
      ->setCommentFormID($comment_form_id)
      ->setUser($user)
      ->setMock($mock)
      ->setImageID($this->imageID)
      ->setViewMode('history');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs
      ->addTextCrumb('M'.$mock->getID(), '/M'.$mock->getID())
      ->addTextCrumb('Image History', $request->getRequestURI());

    $content = array(
      $crumbs,
      $header,
      $output->render(),
    );

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => 'M'.$mock->getID().' '.$title,
        'device' => true,
        'pageObjects' => array($mock->getPHID()),
      ));
  }

}
