<?php

/**
 * @group pholio
 */
final class PholioInlineSaveController extends PholioController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $mock = id(new PholioMockQuery())
      ->setViewer($user)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW
        ))
      ->withIDs(array($request->getInt('mockID')))
      ->executeOne();

    if (!$mock) {
      return new Aphront404Response();
    }

    $draft = id(new PholioTransactionComment());
    $draft->setImageID($request->getInt('imageID'));
    $draft->setX($request->getInt('startX'));
    $draft->setY($request->getInt('startY'));

    $draft->setCommentVersion(1);
    $draft->setAuthorPHID($user->getPHID());
    $draft->setEditPolicy($user->getPHID());
    $draft->setViewPolicy(PhabricatorPolicies::POLICY_PUBLIC);

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    $draft->setContentSource($content_source);

    $draft->setWidth($request->getInt('endX') - $request->getInt('startX'));
    $draft->setHeight($request->getInt('endY') - $request->getInt('startY'));

    $draft->setContent($request->getStr('comment'));

    $draft->save();

    return id(new AphrontAjaxResponse())->setContent(array());
  }

}
