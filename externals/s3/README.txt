AMAZON S3 PHP CLASS


USING THE CLASS

OO method (e,g; $s3->getObject(...)):
$s3 = new S3(awsAccessKey, awsSecretKey);

Statically (e,g; S3::getObject(...)):
S3::setAuth(awsAccessKey, awsSecretKey);


For class documentation see:
http://undesigned.org.za/files/s3-class-documentation/index.html


OBJECTS


Put an object from a string:
	$s3->putObject($string, $bucketName, $uploadName, S3::ACL_PUBLIC_READ)
	Legacy function: $s3->putObjectString($string, $bucketName, $uploadName, S3::ACL_PUBLIC_READ)


Put an object from a file:
	$s3->putObject($s3->inputFile($file, false), $bucketName, $uploadName, S3::ACL_PUBLIC_READ)
	Legacy function: $s3->putObjectFile($uploadFile, $bucketName, $uploadName, S3::ACL_PUBLIC_READ)


Put an object from a resource (buffer/file size is required):
	Please note: the resource will be fclose()'d automatically
	$s3->putObject($s3->inputResource(fopen($file, 'rb'), filesize($file)), $bucketName, $uploadName, S3::ACL_PUBLIC_READ)


Get an object:
	$s3->getObject($bucketName, $uploadName)


Save an object to file:
	$s3->getObject($bucketName, $uploadName, $saveName)


Save an object to a resource of any type:
	$s3->getObject($bucketName, $uploadName, fopen('savefile.txt', 'wb'))


Copy an object:
	$s3->copyObject($srcBucket, $srcName, $bucketName, $saveName, $metaHeaders = array(), $requestHeaders = array())


Delete an object:
	$s3->deleteObject($bucketName, $uploadName)



BUCKETS


Get a list of buckets:
	$s3->listBuckets()  // Simple bucket list
	$s3->listBuckets(true)  // Detailed bucket list


Create a public-read bucket:
	$s3->putBucket($bucketName, S3::ACL_PUBLIC_READ)
	$s3->putBucket($bucketName, S3::ACL_PUBLIC_READ, 'EU') // EU-hosted bucket


Get the contents of a bucket:
	$s3->getBucket($bucketName)


Get a bucket's location:
	$s3->getBucketLocation($bucketName)


Delete a bucket:
	$s3->deleteBucket($bucketName)




KNOWN ISSUES

	Files larger than 2GB are not supported on 32 bit systems due to PHP’s signed integer problem



MORE INFORMATION


	Project URL:
	http://undesigned.org.za/2007/10/22/amazon-s3-php-class

	Class documentation:
	http://undesigned.org.za/files/s3-class-documentation/index.html

	Bug reports:
	https://github.com/tpyo/amazon-s3-php-class/issues

	Amazon S3 documentation:
	http://docs.amazonwebservices.com/AmazonS3/2006-03-01/


EOF
