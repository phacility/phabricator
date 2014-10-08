<?php

/**
 * Model of an Attachment
 */
class MimeMailParser_attachment {

	/**
	 * @var $filename Filename
	 */
	public  $filename;
	/**
	 * @var $content_type Mime Type
	 */
	public  $content_type;
	/**
	 * @var $content File Content
	 */
	private  $content;
	/**
	 * @var $extension Filename extension
	 */
	private $extension;
	/**
	 * @var $content_disposition Content-Disposition (attachment or inline)
	 */
	public $content_disposition;
	/**
	 * @var $headers An Array of the attachment headers
	 */
	public $headers;
	
	private  $stream;

	public function __construct($filename, $content_type, $stream, $content_disposition = 'attachment', $headers = array()) {
		$this->filename = $filename;
		$this->content_type = $content_type;
		$this->stream = $stream;
		$this->content = null;
		$this->content_disposition = $content_disposition;
		$this->headers = $headers;
	}
	
	/**
	 * retrieve the attachment filename
	 * @return String
	 */
	public function getFilename() {
		return $this->filename;
	}
	
	/**
	 * Retrieve the Attachment Content-Type
	 * @return String
	 */
	public function getContentType() {
		return $this->content_type;
	}
	
	/**
	 * Retrieve the Attachment Content-Disposition
	 * @return String
	 */
	public function getContentDisposition() {
		return $this->content_disposition;
	}
	
	/**
	 * Retrieve the Attachment Headers
	 * @return String
	 */
	public function getHeaders() {
		return $this->headers;
	}
	
	/**
	 * Retrieve the file extension
	 * @return String
	 */
	public function getFileExtension() {
		if (!$this->extension) {
			$ext = substr(strrchr($this->filename, '.'), 1);
			if ($ext == 'gz') {
				// special case, tar.gz
				// todo: other special cases?
				$ext = preg_match("/\.tar\.gz$/i", $ext) ? 'tar.gz' : 'gz';
			}
			$this->extension = $ext;
		}
		return $this->extension;
	}
	
	/**
	 * Read the contents a few bytes at a time until completed
	 * Once read to completion, it always returns false
	 * @return String
	 * @param $bytes Int[optional]
	 */
	public function read($bytes = 2082) {
		return feof($this->stream) ? false : fread($this->stream, $bytes);
	}
	
	/**
	 * Retrieve the file content in one go
	 * Once you retrieve the content you cannot use MimeMailParser_attachment::read()
	 * @return String
	 */
	public function getContent() {
		if ($this->content === null) {
			fseek($this->stream, 0);
			while(($buf = $this->read()) !== false) { 
				$this->content .= $buf; 
			}
		}
		return $this->content;
	}
	
	/**
	 * Allow the properties 
	 * 	MimeMailParser_attachment::$name,
	 * 	MimeMailParser_attachment::$extension 
	 * to be retrieved as public properties
	 * @param $name Object
	 */
	public function __get($name) {
		if ($name == 'content') {
			return $this->getContent();
		} else if ($name == 'extension') {
			return $this->getFileExtension();
		}
		return null;
	}
	
}

?>
