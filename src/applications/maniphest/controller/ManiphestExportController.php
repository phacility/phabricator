<?php

/**
 * @group maniphest
 */
final class ManiphestExportController extends ManiphestController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = $data['key'];
    return $this;
  }

  /**
   * @phutil-external-symbol class PHPExcel
   * @phutil-external-symbol class PHPExcel_IOFactory
   * @phutil-external-symbol class PHPExcel_Style_NumberFormat
   * @phutil-external-symbol class PHPExcel_Cell_DataType
   */
  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $ok = @include_once 'PHPExcel.php';
    if (!$ok) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);

      $inst1 = pht(
        'This system does not have PHPExcel installed. This software '.
        'component is required to export tasks to Excel. Have your system '.
        'administrator install it from:');

      $inst2 = pht(
        'Your PHP "include_path" needs to be updated to include the '.
        'PHPExcel Classes directory.');

      $dialog->setTitle(pht('Excel Export Not Configured'));
      $dialog->appendChild(hsprintf(
        '<p>%s</p>'.
        '<br />'.
        '<p>'.
          '<a href="http://www.phpexcel.net/">http://www.phpexcel.net/</a>'.
        '</p>'.
        '<br />'.
        '<p>%s</p>',
        $inst1,
        $inst2));

      $dialog->addCancelButton('/maniphest/');
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    // TODO: PHPExcel has a dependency on the PHP zip extension. We should test
    // for that here, since it fatals if we don't have the ZipArchive class.

    $query = id(new PhabricatorSearchQuery())->loadOneWhere(
      'queryKey = %s',
      $this->key);
    if (!$query) {
      return new Aphront404Response();
    }

    $formats = ManiphestExcelFormat::loadAllFormats();
    $export_formats = array();
    foreach ($formats as $format_class => $format_object) {
      $export_formats[$format_class] = $format_object->getName();
    }

    if (!$request->isDialogFormPost()) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);

      $dialog->setTitle(pht('Export Tasks to Excel'));
      $dialog->appendChild(phutil_tag('p', array(), pht(
        'Do you want to export the query results to Excel?')));

      $form = id(new AphrontFormLayoutView())
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Format:'))
            ->setName("excel-format")
            ->setOptions($export_formats));

      $dialog->appendChild($form);

      $dialog->addCancelButton('/maniphest/');
      $dialog->addSubmitButton(pht('Export to Excel'));
      return id(new AphrontDialogResponse())->setDialog($dialog);

    }

    $format = idx($formats, $request->getStr("excel-format"));
    if ($format === null) {
      throw new Exception('Excel format object not found.');
    }

    $query->setParameter('limit',   null);
    $query->setParameter('offset',  null);
    $query->setParameter('order',   'p');
    $query->setParameter('group',   'n');

    list($tasks, $handles) = ManiphestTaskListController::loadTasks(
      $query,
      $user);
    // Ungroup tasks.
    $tasks = array_mergev($tasks);

    $all_projects = array_mergev(mpull($tasks, 'getProjectPHIDs'));
    $project_handles = $this->loadViewerHandles($all_projects);
    $handles += $project_handles;

    $workbook = new PHPExcel();
    $format->buildWorkbook($workbook, $tasks, $handles, $user);
    $writer = PHPExcel_IOFactory::createWriter($workbook, 'Excel2007');

    ob_start();
    $writer->save('php://output');
    $data = ob_get_clean();

    $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    return id(new AphrontFileResponse())
      ->setMimeType($mime)
      ->setDownload($format->getFileName().'.xlsx')
      ->setContent($data);
  }

}
