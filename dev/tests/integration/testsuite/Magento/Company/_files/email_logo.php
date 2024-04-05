<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();
/** @var $database Database */
$database = $objectManager->get(Database::class);

/** @var $mediaDirectory WriteInterface */
$mediaDirectory = $objectManager->get(Filesystem::class)
    ->getDirectoryWrite(DirectoryList::MEDIA);
$targetPath = str_replace('/', DIRECTORY_SEPARATOR, 'email/logo');
$mediaDirectory->create($targetPath);
$targetPath = rtrim($mediaDirectory->getAbsolutePath(), '/') . DIRECTORY_SEPARATOR . $targetPath
    . DIRECTORY_SEPARATOR . 'magento_logo.jpg';
$sourceFilePath = __DIR__ . DIRECTORY_SEPARATOR . 'magento_logo.jpg';
$mediaDirectory->getDriver()->filePutContents(
    $targetPath,
    file_get_contents($sourceFilePath)
);
$database->saveFile($targetPath);
