<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\NegotiableQuote\Model\CommentManagement;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();
/** @var Filesystem $filesystem */
$filesystem = $objectManager->get(Filesystem::class);
$mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
$attachmentDir = $mediaDirectory->getAbsolutePath(CommentManagement::ATTACHMENTS_FOLDER);
// recreate attachment directory
$mediaDirectory->delete($attachmentDir);
$mediaDirectory->create($attachmentDir);
