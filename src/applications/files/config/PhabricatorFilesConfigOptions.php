<?php

final class PhabricatorFilesConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Files");
  }

  public function getDescription() {
    return pht("Configure files and file storage.");
  }

  public function getOptions() {

    $viewable_default = array(
      'image/jpeg'  => 'image/jpeg',
      'image/jpg'   => 'image/jpg',
      'image/png'   => 'image/png',
      'image/gif'   => 'image/gif',
      'text/plain'  => 'text/plain; charset=utf-8',
      'text/x-diff' => 'text/plain; charset=utf-8',

      // ".ico" favicon files, which have mime type diversity. See:
      // http://en.wikipedia.org/wiki/ICO_(file_format)#MIME_type
      'image/x-ico'               => 'image/x-icon',
      'image/x-icon'              => 'image/x-icon',
      'image/vnd.microsoft.icon'  => 'image/x-icon',
    );

    $image_default = array(
      'image/jpeg'                => true,
      'image/jpg'                 => true,
      'image/png'                 => true,
      'image/gif'                 => true,
      'image/x-ico'               => true,
      'image/x-icon'              => true,
      'image/vnd.microsoft.icon'  => true,
    );

    // largely lifted from http://en.wikipedia.org/wiki/Internet_media_type
    $icon_default = array(
      // audio file icon
      'audio/basic' => 'docs_audio',
      'audio/L24' => 'docs_audio',
      'audio/mp4' => 'docs_audio',
      'audio/mpeg' => 'docs_audio',
      'audio/ogg' => 'docs_audio',
      'audio/vorbis' => 'docs_audio',
      'audio/vnd.rn-realaudio' => 'docs_audio',
      'audio/vnd.wave' => 'docs_audio',
      'audio/webm' => 'docs_audio',
      // movie file icon
      'video/mpeg' => 'docs_movie',
      'video/mp4' => 'docs_movie',
      'video/ogg' => 'docs_movie',
      'video/quicktime' => 'docs_movie',
      'video/webm' => 'docs_movie',
      'video/x-matroska' => 'docs_movie',
      'video/x-ms-wmv' => 'docs_movie',
      'video/x-flv' => 'docs_movie',
      // pdf file icon
      'application/pdf' => 'docs_pdf',
      // zip file icon
      'application/zip' => 'docs_zip',
      // msword icon
      'application/msword' => 'docs_doc',
    ) + array_fill_keys(array_keys($image_default), 'docs_image');

    return array(
      $this->newOption('files.viewable-mime-types', 'wild', $viewable_default)
        ->setSummary(
          pht('Configure which MIME types are viewable in the browser.'))
        ->setDescription(
          pht(
            'Configure which uploaded file types may be viewed directly '.
            'in the browser. Other file types will be downloaded instead '.
            'of displayed. This is mainly a usability consideration, since '.
            'browsers tend to freak out when viewing enormous binary files.'.
            "\n\n".
            'The keys in this map are vieweable MIME types; the values are '.
            'the MIME type sthey are delivered as when they are viewed in '.
            'the browser.')),
      $this->newOption('files.image-mime-types', 'set', $image_default)
        ->setSummary(pht('Configure which MIME types are images.'))
        ->setDescription(
          pht(
            'List of MIME types which can be used as the `src` for an '.
            '`<img />` tag.')),
      $this->newOption('files.icon-mime-types', 'wild', $icon_default)
        ->setSummary(pht('Configure which MIME types map to which icons.'))
        ->setDescription(
          pht(
            'Map of MIME type to icon name. MIME types which can not be '.
            'found default to icon `doc_files`.')),
      $this->newOption('storage.mysql-engine.max-size', 'int', 1000000)
        ->setSummary(
          pht(
            'Configure the largest file which will be put into the MySQL '.
            'storage engine.')),
      $this->newOption('storage.local-disk.path', 'string', null)
        ->setSummary(pht('Local storage disk path.'))
        ->setDescription(
          pht(
            "Phabricator provides a local disk storage engine, which just ".
            "writes files to some directory on local disk. The webserver ".
            "must have read/write permissions on this directory. This is ".
            "straightforward and suitable for most installs, but will not ".
            "scale past one web frontend unless the path is actually an NFS ".
            "mount, since you'll end up with some of the files written to ".
            "each web frontend and no way for them to share. To use the ".
            "local disk storage engine, specify the path to a directory ".
            "here. To disable it, specify null.")),
     $this->newOption('storage.s3.bucket', 'string', null)
        ->setSummary(pht('Amazon S3 bucket.'))
        ->setDescription(
          pht(
            "Set this to a valid Amazon S3 bucket to store files there. You ".
            "must also configure S3 access keys in the 'Amazon Web Services' ".
            "group.")),
     $this->newOption(
       'storage.engine-selector',
       'class',
       'PhabricatorDefaultFileStorageEngineSelector')
        ->setBaseClass('PhabricatorFileStorageEngineSelector')
        ->setSummary(pht('Storage engine selector.'))
        ->setDescription(
          pht(
            "Phabricator uses a storage engine selector to choose which ".
            "storage engine to use when writing file data. If you add new ".
            "storage engines or want to provide very custom rules (e.g., ".
            "write images to one storage engine and other files to a ".
            "different one), you can provide an alternate implementation ".
            "here. The default engine will use choose MySQL, Local Disk, and ".
            "S3, in that order, if they have valid configurations above and ".
            "a file fits within configured limits.")),
     $this->newOption('storage.upload-size-limit', 'string', null)
        ->setSummary(
          pht('Limit to users in interfaces which allow uploading.'))
        ->setDescription(
          pht(
            "Set the size of the largest file a user may upload. This is ".
            "used to render text like 'Maximum file size: 10MB' on ".
            "interfaces where users can upload files, and files larger than ".
            "this size will be rejected. \n\n".
            "Specify this limit in bytes, or using a 'K', 'M', or 'G' ".
            "suffix.\n\n".
            "NOTE: Setting this to a large size is **NOT** sufficient to ".
            "allow users to upload large files. You must also configure a ".
            "number of other settings. To configure file upload limits, ".
            "consult the article 'Configuring File Upload Limits' in the ".
            "documentation. Once you've configured some limit across all ".
            "levels of the server, you can set this limit to an appropriate ".
            "value and the UI will then reflect the actual configured ".
            "limit."))
        ->addExample('10M', pht("Valid setting.")),
     $this->newOption('files.enable-imagemagick', 'bool', false)
       ->setBoolOptions(
         array(
           pht('Enable'),
           pht('Disable')
         ))->setDescription(
             pht("This option will enable animated gif images".
                  "to be set as profile pictures. The \'convert\' binary ".
                  "should be available to the webserver for this to work")),

    );
  }

}
