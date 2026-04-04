<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\Media;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class AssetCopyService
{
    private \Magento\Framework\Filesystem\Directory\WriteInterface $mediaDirectory;

    public function __construct(
        Filesystem $filesystem,
        private readonly AssetStorage $assetStorage
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    public function duplicateIfManaged(?string $value, string $subDirectory): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (!$this->assetStorage->isManagedAsset($value)) {
            return $value;
        }

        $normalizedSource = ltrim(str_replace('\\', '/', $value), '/');
        if (!$this->mediaDirectory->isExist($normalizedSource)) {
            return $value;
        }

        $targetDir = trim(AssetStorage::BASE_MEDIA_PATH . '/' . trim($subDirectory, '/'), '/');
        $this->mediaDirectory->create($targetDir);

        $sourceInfo = pathinfo($normalizedSource);
        $filename = (string)($sourceInfo['filename'] ?? 'asset');
        $extension = strtolower((string)($sourceInfo['extension'] ?? ''));
        $safeFilename = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', $filename) ?: 'asset';

        $targetRelativePath = $this->buildUniqueTargetPath($targetDir, $safeFilename, $extension);
        $targetAbsolutePath = $this->mediaDirectory->getAbsolutePath($targetRelativePath);
        $sourceAbsolutePath = $this->mediaDirectory->getAbsolutePath($normalizedSource);

        if (!@copy($sourceAbsolutePath, $targetAbsolutePath)) {
            throw new LocalizedException(__('Unable to duplicate managed asset "%1".', $value));
        }

        return $targetRelativePath;
    }

    private function buildUniqueTargetPath(string $targetDir, string $filename, string $extension): string
    {
        $suffix = date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $basename = $filename . '-copy-' . $suffix;
        $relative = $targetDir . '/' . $basename;

        if ($extension !== '') {
            $relative .= '.' . $extension;
        }

        return $relative;
    }
}
