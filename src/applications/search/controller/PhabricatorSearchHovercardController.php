<?php

final class PhabricatorSearchHovercardController
  extends PhabricatorSearchBaseController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $cards = $request->getJSONMap('cards');

    // If object names are provided, look them up and pretend they were
    // passed as additional PHIDs. This is primarily useful for debugging,
    // since you don't have to go look up user PHIDs to preview their
    // hovercards.
    $names = $request->getStrList('names');
    if ($names) {
      $named_objects = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withNames($names)
        ->execute();

      foreach ($named_objects as $object) {
        $cards[] = array(
          'objectPHID' => $object->getPHID(),
        );
      }
    }

    $object_phids = array();
    $handle_phids = array();
    $context_phids = array();
    foreach ($cards as $card) {
      $object_phid = idx($card, 'objectPHID');

      $handle_phids[] = $object_phid;
      $object_phids[] = $object_phid;

      $context_phid = idx($card, 'contextPHID');

      if ($context_phid) {
        $object_phids[] = $context_phid;
        $context_phids[] = $context_phid;
      }
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($handle_phids)
      ->execute();

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs($object_phids)
      ->execute();
    $objects = mpull($objects, null, 'getPHID');

    $context_objects = array_select_keys($objects, $context_phids);

    if ($context_objects) {
      PhabricatorPolicyFilterSet::loadHandleViewCapabilities(
        $viewer,
        $handles,
        $context_objects);
    }

    $extensions =
      PhabricatorHovercardEngineExtension::getAllEnabledExtensions();

    $extension_maps = array();
    foreach ($extensions as $extension_key => $extension) {
      $extension->setViewer($viewer);

      $extension_phids = array();
      foreach ($objects as $phid => $object) {
        if ($extension->canRenderObjectHovercard($object)) {
          $extension_phids[$phid] = $phid;
        }
      }

      $extension_maps[$extension_key] = $extension_phids;
    }

    $extension_data = array();
    foreach ($extensions as $extension_key => $extension) {
      $extension_phids = $extension_maps[$extension_key];
      if (!$extension_phids) {
        unset($extensions[$extension_key]);
        continue;
      }

      $extension_data[$extension_key] = $extension->willRenderHovercards(
        array_select_keys($objects, $extension_phids));
    }

    $results = array();
    foreach ($cards as $card_key => $card) {
      $object_phid = $card['objectPHID'];

      $handle = $handles[$object_phid];
      $object = idx($objects, $object_phid);

      $context_phid = idx($card, 'contextPHID');
      if ($context_phid) {
        $context_object = idx($context_objects, $context_phid);
      } else {
        $context_object = null;
      }

      $hovercard = id(new PHUIHovercardView())
        ->setUser($viewer)
        ->setObjectHandle($handle);

      if ($context_object) {
        if ($handle->hasCapabilities()) {
          if (!$handle->hasViewCapability($context_object)) {
            $hovercard->setIsExiled(true);
          }
        }
      }

      if ($object) {
        $hovercard->setObject($object);

        foreach ($extension_maps as $extension_key => $extension_phids) {
          if (isset($extension_phids[$object_phid])) {
            $extensions[$extension_key]->renderHovercard(
              $hovercard,
              $handle,
              $object,
              $extension_data[$extension_key]);
          }
        }
      }

      $results[$card_key] = $hovercard;
    }

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent(
        array(
          'cards' => $results,
        ));
    }

    foreach ($results as $result_key => $hovercard) {
      $results[$result_key] = phutil_tag('div',
        array(
          'class' => 'ml',
        ),
        $hovercard);
    }

    return $this->newPage()
      ->appendChild($results)
      ->setShowFooter(false);
  }

}
