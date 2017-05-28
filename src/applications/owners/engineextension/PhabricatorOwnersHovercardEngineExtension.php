<?php

final class PhabricatorOwnersHovercardEngineExtension
  extends PhabricatorHovercardEngineExtension {

  const EXTENSIONKEY = 'owners';

  public function isExtensionEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorOwnersApplication');
  }

  public function getExtensionName() {
    return pht('Owner Packages');
  }

  public function canRenderObjectHovercard($object) {
    return ($object instanceof PhabricatorOwnersPackage);
  }

  public function willRenderHovercards(array $objects) {
    $viewer = $this->getViewer();
    $phids = mpull($objects, 'getPHID');

    $packages = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
    $packages = mpull($packages, null, 'getPHID');

    return array(
      'packages' => $packages,
    );
  }

  public function renderHovercard(
    PHUIHovercardView $hovercard,
    PhabricatorObjectHandle $handle,
    $object,
    $data) {

    $viewer = $this->getViewer();

    $package = idx($data['packages'], $object->getPHID());
    if (!$package) {
      return;
    }

    $title = pht('%s: %s', 'O'.$package->getID(), $package->getName());
    $hovercard->setTitle($title);

    $dominion = $package->getDominion();
    $dominion_map = PhabricatorOwnersPackage::getDominionOptionsMap();
    $spec = idx($dominion_map, $dominion, array());
    $name = idx($spec, 'short', $dominion);
    $hovercard->addField(pht('Dominion'), $name);

    $auto = $package->getAutoReview();
    $autoreview_map = PhabricatorOwnersPackage::getAutoreviewOptionsMap();
    $spec = idx($autoreview_map, $auto, array());
    $name = idx($spec, 'name', $auto);
    $hovercard->addField(pht('Auto Review'), $name);

    if ($package->isArchived()) {
      $tag = id(new PHUITagView())
        ->setName(pht('Archived'))
        ->setColor(PHUITagView::COLOR_INDIGO)
        ->setType(PHUITagView::TYPE_OBJECT);
      $hovercard->addTag($tag);
    }

    $owner_phids = $package->getOwnerPHIDs();

    $hovercard->addField(
      pht('Owners'),
      $viewer->renderHandleList($owner_phids)->setAsInline(true));

    $description = $package->getDescription();
    if (strlen($description)) {
      $description = id(new PhutilUTF8StringTruncator())
        ->setMaximumGlyphs(120)
        ->truncateString($description);

      $hovercard->addField(pht('Description'), $description);
    }

  }

}
