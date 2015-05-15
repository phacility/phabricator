<?php

final class PhabricatorFileTransformListController
  extends PhabricatorFileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    $monogram = $file->getMonogram();

    $xdst = id(new PhabricatorTransformedFile())->loadAllWhere(
      'transformedPHID = %s',
      $file->getPHID());

    $dst_rows = array();
    foreach ($xdst as $source) {
      $dst_rows[] = array(
        $source->getTransform(),
        $viewer->renderHandle($source->getOriginalPHID()),
      );
    }
    $dst_table = id(new AphrontTableView($dst_rows))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Source'),
        ))
      ->setColumnClasses(
        array(
          '',
          'wide',
        ))
      ->setNoDataString(
        pht(
          'This file was not created by transforming another file.'));

    $xsrc = id(new PhabricatorTransformedFile())->loadAllWhere(
      'originalPHID = %s',
      $file->getPHID());
    $xsrc = mpull($xsrc, 'getTransformedPHID', 'getTransform');

    $src_rows = array();
    $xforms = PhabricatorFileTransform::getAllTransforms();
    foreach ($xforms as $xform) {
      $dst_phid = idx($xsrc, $xform->getTransformKey());

      if ($xform->canApplyTransform($file)) {
        $can_apply = pht('Yes');

        $view_href = $file->getURIForTransform($xform);
        $view_href = new PhutilURI($view_href);
        $view_href->setQueryParam('regenerate', 'true');

        $view_text = pht('Regenerate');

        $view_link = phutil_tag(
          'a',
          array(
            'class' => 'small grey button',
            'href' => $view_href,
          ),
          $view_text);
      } else {
        $can_apply = phutil_tag('em', array(), pht('No'));
        $view_link = phutil_tag('em', array(), pht('None'));
      }

      if ($dst_phid) {
        $dst_link = $viewer->renderHandle($dst_phid);
      } else {
        $dst_link = phutil_tag('em', array(), pht('None'));
      }

      $src_rows[] = array(
        $xform->getTransformName(),
        $xform->getTransformKey(),
        $can_apply,
        $dst_link,
        $view_link,
      );
    }

    $src_table = id(new AphrontTableView($src_rows))
      ->setHeaders(
        array(
          pht('Name'),
          pht('Key'),
          pht('Supported'),
          pht('Transform'),
          pht('View'),
        ))
      ->setColumnClasses(
        array(
          'wide',
          '',
          '',
          '',
          'action',
        ));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($monogram, '/'.$monogram);
    $crumbs->addTextCrumb(pht('Transforms'));

    $dst_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('File Sources'))
      ->appendChild($dst_table);

    $src_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Available Transforms'))
      ->appendChild($src_table);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $dst_box,
        $src_box,
      ),
      array(
        'title' => array(
          pht('%s %s', $monogram, $file->getName()),
          pht('Tranforms'),
        ),
      ));
  }
}
