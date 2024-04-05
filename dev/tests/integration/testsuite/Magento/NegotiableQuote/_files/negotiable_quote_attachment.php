<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\NegotiableQuote\Model\CommentManagement;
use Magento\NegotiableQuote\Model\Attachment\Uploader as AttachmentUploader;
use Magento\Framework\File\Mime;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

$imageName = 'magento_image.jpg';
$imagePath = INTEGRATION_TESTS_DIR . '/testsuite/Magento/Catalog/_files/' . $imageName;
/** @var Mime $mimeTypeResolver */
$mimeTypeResolver = $objectManager->create(Mime::class);
$fileType = $mimeTypeResolver->getMimeType($imagePath);

/** @var Filesystem $filesystem */
$filesystem = $objectManager->get(Filesystem::class);
$mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
$attachmentDir = $mediaDirectory->getAbsolutePath(CommentManagement::ATTACHMENTS_FOLDER);
$mediaDirectory->create($attachmentDir);

$filePath = AttachmentUploader::getDispersionPath($imageName);
$mediaDirectory->create($attachmentDir . $filePath);
$filePath .= DIRECTORY_SEPARATOR . AttachmentUploader::getNewFileName($imageName);

$imageContent = file_get_contents($imagePath);
$mediaDirectory->getDriver()->filePutContents($attachmentDir . $filePath, $imageContent);

return [$imageName, $fileType, $filePath];
