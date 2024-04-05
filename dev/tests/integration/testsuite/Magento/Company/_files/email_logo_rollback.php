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

/** @var $database Database */
$database = Bootstrap::getObjectManager()->get(Database::class);

/** @var WriteInterface $mediaDirectory */
$mediaDirectory = Bootstrap::getObjectManager()
    ->get(Filesystem::class)
    ->getDirectoryWrite(DirectoryList::MEDIA);

$mediaDirectory->delete('email/logo');

$database->deleteFolder('email/logo');
