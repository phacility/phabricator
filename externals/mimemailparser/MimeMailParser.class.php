<?php

require_once('attachment.class.php');

/**
 * Fast Mime Mail parser Class using PHP's MailParse Extension
 * @author gabe@fijiwebdesign.com
 * @url http://www.fijiwebdesign.com/
 * @license http://creativecommons.org/licenses/by-sa/3.0/us/
 * @version $Id$
 */
class MimeMailParser {

	/**
	 * PHP MimeParser Resource ID
	 */
	public $resource;

	/**
	 * A file pointer to email
	 */
	public $stream;

	/**
	 * A text of an email
	 */
	public $data;

	/**
	 * Stream Resources for Attachments
	 */
	public $attachment_streams;

	/**
	 * Inialize some stuff
	 * @return
	 */
	public function __construct() {
		$this->attachment_streams = array();
	}

	/**
	 * Free the held resouces
	 * @return void
	 */
	public function __destruct() {
		// clear the email file resource
		if (is_resource($this->stream)) {
			fclose($this->stream);
		}
		// clear the MailParse resource
		if (is_resource($this->resource)) {
			mailparse_msg_free($this->resource);
		}
		// remove attachment resources
		foreach($this->attachment_streams as $stream) {
			fclose($stream);
		}
	}

	/**
	 * Set the file path we use to get the email text
	 * @return Object MimeMailParser Instance
	 * @param $mail_path Object
	 */
	public function setPath($path) {
		// should parse message incrementally from file
		$this->resource = mailparse_msg_parse_file($path);
		$this->stream = fopen($path, 'r');
		$this->parse();
		return $this;
	}

	/**
	 * Set the Stream resource we use to get the email text
	 * @return Object MimeMailParser Instance
	 * @param $stream Resource
	 */
	public function setStream($stream) {

		// streams have to be cached to file first
		if (get_resource_type($stream) == 'stream') {
			$tmp_fp = tmpfile();
			if ($tmp_fp) {
				while(!feof($stream)) {
					fwrite($tmp_fp, fread($stream, 2028));
				}
				fseek($tmp_fp, 0);
				$this->stream =& $tmp_fp;
			} else {
				throw new Exception('Could not create temporary files for attachments. Your tmp directory may be unwritable by PHP.');
				return false;
			}
			fclose($stream);
		} else {
			$this->stream = $stream;
		}

		$this->resource = mailparse_msg_create();
		// parses the message incrementally low memory usage but slower
		while(!feof($this->stream)) {
			mailparse_msg_parse($this->resource, fread($this->stream, 2082));
		}
		$this->parse();
		return $this;
	}

	/**
	 * Set the email text
	 * @return Object MimeMailParser Instance
	 * @param $data String
	 */
	public function setText($data) {
	// NOTE: This has been modified for Phabricator. If the input data does not
	// end in a newline, Mailparse fails to include the last line in the mail
	// body. This happens somewhere deep, deep inside the mailparse extension,
	// so adding a newline here seems like the most straightforward fix.
	if (!preg_match('/\n\z/', $data)) {
	  $data = $data."\n";
	}

		$this->resource = mailparse_msg_create();
		// does not parse incrementally, fast memory hog might explode
		mailparse_msg_parse($this->resource, $data);
		$this->data = $data;
		$this->parse();
		return $this;
	}

	/**
	 * Parse the Message into parts
	 * @return void
	 * @private
	 */
	private function parse() {
		$structure = mailparse_msg_get_structure($this->resource);
		$this->parts = array();
		foreach($structure as $part_id) {
			$part = mailparse_msg_get_part($this->resource, $part_id);
			$this->parts[$part_id] = mailparse_msg_get_part_data($part);
		}
	}

	/**
	 * Retrieve the Email Headers
	 * @return Array
	 */
	public function getHeaders() {
		if (isset($this->parts[1])) {
			return $this->getPartHeaders($this->parts[1]);
		} else {
			throw new Exception('MimeMailParser::setPath() or MimeMailParser::setText() must be called before retrieving email headers.');
		}
		return false;
	}
	/**
	 * Retrieve the raw Email Headers
	 * @return string
	 */
	public function getHeadersRaw() {
		if (isset($this->parts[1])) {
			return $this->getPartHeaderRaw($this->parts[1]);
		} else {
			throw new Exception('MimeMailParser::setPath() or MimeMailParser::setText() must be called before retrieving email headers.');
		}
		return false;
	}

	/**
	 * Retrieve a specific Email Header
	 * @return String
	 * @param $name String Header name
	 */
	public function getHeader($name) {
		if (isset($this->parts[1])) {
			$headers = $this->getPartHeaders($this->parts[1]);
			if (isset($headers[$name])) {
				return $headers[$name];
			}
		} else {
			throw new Exception('MimeMailParser::setPath() or MimeMailParser::setText() must be called before retrieving email headers.');
		}
		return false;
	}

	/**
	 * Returns the email message body in the specified format
	 * @return Mixed String Body or False if not found
	 * @param $type Object[optional]
	 */
	public function getMessageBody($type = 'text') {

	  // NOTE: This function has been modified for Phabricator. The default
	  // implementation returns the last matching part, which throws away text
	  // for many emails. Instead, we concatenate all matching parts. See
	  // issue 22 for discussion:
	  // http://code.google.com/p/php-mime-mail-parser/issues/detail?id=22

		$body = false;
		$mime_types = array(
			'text'=> 'text/plain',
			'html'=> 'text/html'
		);
		if (in_array($type, array_keys($mime_types))) {
			foreach($this->parts as $part) {
			$disposition = $this->getPartContentDisposition($part);
			if ($disposition == 'attachment') {
			  // text/plain parts with "Content-Disposition: attachment" are
			  // attachments, not part of the text body.
			  continue;
			}
				if ($this->getPartContentType($part) == $mime_types[$type]) {
		  $headers = $this->getPartHeaders($part);
		  // Concatenate all the matching parts into the body text. For example,
		  // if a user sends a message with some text, then an image, and then
		  // some more text, the text body of the email gets split over several
		  // attachments.
					$body .= $this->decode(
					  $this->getPartBody($part),
					  array_key_exists('content-transfer-encoding', $headers)
						? $headers['content-transfer-encoding']
						: '');
				}
			}
		} else {
			throw new Exception('Invalid type specified for MimeMailParser::getMessageBody. "type" can either be text or html.');
		}
		return $body;
	}

	/**
	 * get the headers for the message body part.
	 * @return Array
	 * @param $type Object[optional]
	 */
	public function getMessageBodyHeaders($type = 'text') {
		$headers = false;
		$mime_types = array(
			'text'=> 'text/plain',
			'html'=> 'text/html'
		);
		if (in_array($type, array_keys($mime_types))) {
			foreach($this->parts as $part) {
				if ($this->getPartContentType($part) == $mime_types[$type]) {
					$headers = $this->getPartHeaders($part);
				}
			}
		} else {
			throw new Exception('Invalid type specified for MimeMailParser::getMessageBody. "type" can either be text or html.');
		}
		return $headers;
	}

	/**
	 * Returns the attachments contents in order of appearance
	 * @return Array
	 * @param $type Object[optional]
	 */
	public function getAttachments() {
    // NOTE: This has been modified for Phabricator. Some mail clients do not
    // send attachments with "Content-Disposition" headers.
		$attachments = array();
		$dispositions = array("attachment","inline");
		$non_attachment_types = array("text/plain", "text/html");
		$nonameIter = 0;
		foreach ($this->parts as $part) {
			$disposition = $this->getPartContentDisposition($part);
			$filename = 'noname';
			if (isset($part['disposition-filename'])) {
				$filename = $part['disposition-filename'];
			} elseif (isset($part['content-name'])) {
				// if we have no disposition but we have a content-name, it's a valid attachment.
				// we simulate the presence of an attachment disposition with a disposition filename
				$filename = $part['content-name'];
				$disposition = 'attachment';
			} elseif (!in_array($part['content-type'], $non_attachment_types, true)
				&& substr($part['content-type'], 0, 10) !== 'multipart/'
				) {
				// if we cannot get it with getMessageBody, we assume it is an attachment
				$disposition = 'attachment';
			}

			if (in_array($disposition, $dispositions) && isset($filename) === true) {
				if ($filename == 'noname') {
					$nonameIter++;
					$filename = 'noname'.$nonameIter;
				}
				$attachments[] = new MimeMailParser_attachment(
					$filename,
					$this->getPartContentType($part),
					$this->getAttachmentStream($part),
					$disposition,
					$this->getPartHeaders($part)
				);
			}
		}
		return $attachments;
	}

	/**
	 * Return the Headers for a MIME part
	 * @return Array
	 * @param $part Array
	 */
	private function getPartHeaders($part) {
		if (isset($part['headers'])) {
			return $part['headers'];
		}
		return false;
	}

	/**
	 * Return a Specific Header for a MIME part
	 * @return Array
	 * @param $part Array
	 * @param $header String Header Name
	 */
	private function getPartHeader($part, $header) {
		if (isset($part['headers'][$header])) {
			return $part['headers'][$header];
		}
		return false;
	}

	/**
	 * Return the ContentType of the MIME part
	 * @return String
	 * @param $part Array
	 */
	private function getPartContentType($part) {
		if (isset($part['content-type'])) {
			return $part['content-type'];
		}
		return false;
	}

	/**
	 * Return the Content Disposition
	 * @return String
	 * @param $part Array
	 */
	private function getPartContentDisposition($part) {
		if (isset($part['content-disposition'])) {
			return $part['content-disposition'];
		}
		return false;
	}

	/**
	 * Retrieve the raw Header of a MIME part
	 * @return String
	 * @param $part Object
	 */
	private function getPartHeaderRaw(&$part) {
		$header = '';
		if ($this->stream) {
			$header = $this->getPartHeaderFromFile($part);
		} else if ($this->data) {
			$header = $this->getPartHeaderFromText($part);
		} else {
			throw new Exception('MimeMailParser::setPath() or MimeMailParser::setText() must be called before retrieving email parts.');
		}
		return $header;
	}
	/**
	 * Retrieve the Body of a MIME part
	 * @return String
	 * @param $part Object
	 */
	private function getPartBody(&$part) {
		$body = '';
		if ($this->stream) {
			$body = $this->getPartBodyFromFile($part);
		} else if ($this->data) {
			$body = $this->getPartBodyFromText($part);
		} else {
			throw new Exception('MimeMailParser::setPath() or MimeMailParser::setText() must be called before retrieving email parts.');
		}
		return $body;
	}

	/**
	 * Retrieve the Header from a MIME part from file
	 * @return String Mime Header Part
	 * @param $part Array
	 */
	private function getPartHeaderFromFile(&$part) {
		$start = $part['starting-pos'];
		$end = $part['starting-pos-body'];
		fseek($this->stream, $start, SEEK_SET);
		$header = fread($this->stream, $end-$start);
		return $header;
	}
	/**
	 * Retrieve the Body from a MIME part from file
	 * @return String Mime Body Part
	 * @param $part Array
	 */
	private function getPartBodyFromFile(&$part) {
		$start = $part['starting-pos-body'];
		$end = $part['ending-pos-body'];
		fseek($this->stream, $start, SEEK_SET);
		$body = fread($this->stream, $end-$start);
		return $body;
	}

	/**
	 * Retrieve the Header from a MIME part from text
	 * @return String Mime Header Part
	 * @param $part Array
	 */
	private function getPartHeaderFromText(&$part) {
		$start = $part['starting-pos'];
		$end = $part['starting-pos-body'];
		$header = substr($this->data, $start, $end-$start);
		return $header;
	}
	/**
	 * Retrieve the Body from a MIME part from text
	 * @return String Mime Body Part
	 * @param $part Array
	 */
	private function getPartBodyFromText(&$part) {
		$start = $part['starting-pos-body'];
		$end = $part['ending-pos-body'];
		$body = substr($this->data, $start, $end-$start);
		return $body;
	}

	/**
	 * Read the attachment Body and save temporary file resource
	 * @return String Mime Body Part
	 * @param $part Array
	 */
	private function getAttachmentStream(&$part) {
		$temp_fp = tmpfile();

		array_key_exists('content-transfer-encoding', $part['headers']) ? $encoding = $part['headers']['content-transfer-encoding'] : $encoding = '';

		if ($temp_fp) {
			if ($this->stream) {
				$start = $part['starting-pos-body'];
				$end = $part['ending-pos-body'];
				fseek($this->stream, $start, SEEK_SET);
				$len = $end-$start;
				$written = 0;
				$write = 2028;
				$body = '';
				while($written < $len) {
					if (($written+$write < $len )) {
						$write = $len - $written;
					}
					$part = fread($this->stream, $write);
					fwrite($temp_fp, $this->decode($part, $encoding));
					$written += $write;
				}
			} else if ($this->data) {
				$attachment = $this->decode($this->getPartBodyFromText($part), $encoding);
				fwrite($temp_fp, $attachment, strlen($attachment));
			}
			fseek($temp_fp, 0, SEEK_SET);
		} else {
			throw new Exception('Could not create temporary files for attachments. Your tmp directory may be unwritable by PHP.');
			return false;
		}
		return $temp_fp;
	}


	/**
	 * Decode the string depending on encoding type.
	 * @return String the decoded string.
	 * @param $encodedString	The string in its original encoded state.
	 * @param $encodingType		The encoding type from the Content-Transfer-Encoding header of the part.
	 */
	private function decode($encodedString, $encodingType) {
		if (strtolower($encodingType) == 'base64') {
			return base64_decode($encodedString);
		} else if (strtolower($encodingType) == 'quoted-printable') {
			 return quoted_printable_decode($encodedString);
		} else {
			return $encodedString;
		}
	}

}


?>
