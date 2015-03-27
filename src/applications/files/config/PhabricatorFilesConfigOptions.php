<?php

final class PhabricatorFilesConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Files');
  }

  public function getDescription() {
    return pht('Configure files and file storage.');
  }

  public function getFontIcon() {
    return 'fa-file';
  }

  public function getGroup() {
    return 'apps';
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

      'audio/x-wav'     => 'audio/x-wav',
      'application/ogg' => 'application/ogg',
      'audio/mpeg'      => 'audio/mpeg',
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

    $audio_default = array(
      'audio/x-wav'     => true,
      'application/ogg' => true,
      'audio/mpeg'      => true,
    );

    // largely lifted from http://en.wikipedia.org/wiki/Internet_media_type
    $icon_default = array(
      // audio file icon
      'audio/basic' => 'fa-file-audio-o',
      'audio/L24' => 'fa-file-audio-o',
      'audio/mp4' => 'fa-file-audio-o',
      'audio/mpeg' => 'fa-file-audio-o',
      'audio/ogg' => 'fa-file-audio-o',
      'audio/vorbis' => 'fa-file-audio-o',
      'audio/vnd.rn-realaudio' => 'fa-file-audio-o',
      'audio/vnd.wave' => 'fa-file-audio-o',
      'audio/webm' => 'fa-file-audio-o',
      // movie file icon
      'video/mpeg' => 'fa-file-movie-o',
      'video/mp4' => 'fa-file-movie-o',
      'video/ogg' => 'fa-file-movie-o',
      'video/quicktime' => 'fa-file-movie-o',
      'video/webm' => 'fa-file-movie-o',
      'video/x-matroska' => 'fa-file-movie-o',
      'video/x-ms-wmv' => 'fa-file-movie-o',
      'video/x-flv' => 'fa-file-movie-o',
      // pdf file icon
      'application/pdf' => 'fa-file-pdf-o',
      // zip file icon
      'application/zip' => 'fa-file-zip-o',
      // msword icon
      'application/msword' => 'fa-file-word-o',
      // msexcel
      'application/vnd.ms-excel' => 'fa-file-excel-o',
      // mspowerpoint
      'application/vnd.ms-powerpoint' => 'fa-file-powerpoint-o',

    ) + array_fill_keys(array_keys($image_default), 'fa-file-image-o');

    // NOTE: These options are locked primarily because adding "text/plain"
    // as an image MIME type increases SSRF vulnerability by allowing users
    // to load text files from remote servers as "images" (see T6755 for
    // discussion).

    return array(
      $this->newOption('files.viewable-mime-types', 'wild', $viewable_default)
        ->setLocked(true)
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
            'the MIME types they are delivered as when they are viewed in '.
            'the browser.')),
      $this->newOption('files.image-mime-types', 'set', $image_default)
        ->setLocked(true)
        ->setSummary(pht('Configure which MIME types are images.'))
        ->setDescription(
          pht(
            'List of MIME types which can be used as the `src` for an '.
            '`<img />` tag.')),
      $this->newOption('files.audio-mime-types', 'set', $audio_default)
        ->setLocked(true)
        ->setSummary(pht('Configure which MIME types are audio.'))
        ->setDescription(
          pht(
            'List of MIME types which can be used to render an '.
            '`<audio />` tag.')),
      $this->newOption('files.icon-mime-types', 'wild', $icon_default)
        ->setLocked(true)
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
        ->setLocked(true)
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
        'metamta.files.public-create-email',
        'string',
        null)
        ->setLocked(true)
        ->setLockedMessage(pht(
          'This configuration is deprecated. See description for details.'))
        ->setSummary(pht('DEPRECATED - Allow uploaded files via email.'))
        ->setDescription(
          pht(
            'This config has been deprecated in favor of [[ '.
            '/applications/view/PhabricatorFilesApplication/ | '.
            'application settings ]], which allow for multiple email '.
            'addresses and other functionality.')),
     $this->newOption(
        'metamta.files.subject-prefix',
        'string',
        '[File]')
        ->setDescription(pht('Subject prefix for Files email.')),
     $this->newOption('files.enable-imagemagick', 'bool', false)
       ->setBoolOptions(
         array(
           pht('Enable'),
           pht('Disable'),
         ))
        ->setDescription(
          pht(
            'This option will use Imagemagick to rescale images, so animated '.
            'GIFs can be thumbnailed and set as profile pictures. Imagemagick '.
            'must be installed and the "convert" binary must be available to '.
            'the webserver for this to work.')),

    );
  }

}
