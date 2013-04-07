<?php

/**
 * @group search
 */
final class PhabricatorSearchHovercardController
  extends PhabricatorSearchBaseController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phids = $request->getArr('phids');

    $handle_data = new PhabricatorObjectHandleData($phids);
    $handle_data->setViewer($user);
    $handles = $handle_data->loadHandles();
    $objects = $handle_data->loadObjects();

    $cards = array();

    foreach ($phids as $phid) {
      $handle = $handles[$phid];

      $hovercard = new PhabricatorHovercardView();
      $hovercard->setObjectHandle($handle);

      // Send it to the other side of the world, thanks to PhutilEventEngine
      $event = new PhabricatorEvent(
        PhabricatorEventType::TYPE_UI_DIDRENDERHOVERCARD,
        array(
          'hovercard' => $hovercard,
          'handle'    => $handle,
          'object'    => idx($objects, $phid),
        ));
      $event->setUser($user);
      PhutilEventEngine::dispatchEvent($event);

      $cards[$phid] = $hovercard;
    }

    // Browser-friendly for non-Ajax requests
    if (!$request->isAjax()) {
      foreach ($cards as $key => $hovercard) {
        $cards[$key] = phutil_tag('div',
          array(
            'class' => 'ml',
          ),
          $hovercard);
      }

      return $this->buildApplicationPage(
        $cards,
        array(
          'dust' => true,
        ));
    } else {
      return id(new AphrontAjaxResponse())->setContent(
        array(
          'cards' => $cards,
        ));
    }
  }

}
