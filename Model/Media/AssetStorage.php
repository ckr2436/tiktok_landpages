<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\Media;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\MediaStorage\Model\File\UploaderFactory;

class AssetStorage
{
    public const BASE_MEDIA_PATH = 'pynarae/tiktok_landing_pages/materials';
    public const MAX_FILE_SIZE = 5242880; // 5 MB
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private \Magento\Framework\Filesystem\Directory\WriteInterface $mediaDirectory;

    public function __construct(
        Filesystem $filesystem,
        private readonly UploaderFactory $uploaderFactory
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    public function hasUpload(string $fieldName): bool
    {
        if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
            return false;
        }

        $file = $_FILES[$fieldName];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $name = trim((string)($file['name'] ?? ''));

        return $error !== UPLOAD_ERR_NO_FILE && $name !== '';
    }

    public function saveUploadedImage(string $fieldName, string $subDirectory, ?string $oldValue = null): string
    {
        if (!$this->hasUpload($fieldName)) {
            throw new LocalizedException(__('No uploaded file was found for field "%1".', $fieldName));
        }

        $tmpName = (string)($_FILES[$fieldName]['tmp_name'] ?? '');
        $fileName = (string)($_FILES[$fieldName]['name'] ?? '');
        $fileSize = (int)($_FILES[$fieldName]['size'] ?? 0);
        $error = (int)($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new LocalizedException(__('Image upload failed for "%1".', $fieldName));
        }

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new LocalizedException(__('Invalid uploaded file for "%1".', $fieldName));
        }

        if ($fileSize <= 0) {
            throw new LocalizedException(__('The uploaded image is empty.'));
        }

        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new LocalizedException(__('The uploaded image exceeds the maximum size of %1 MB.', (string)(self::MAX_FILE_SIZE / 1024 / 1024)));
        }

        $extension = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new LocalizedException(__('Only %1 files are allowed.', implode(', ', self::ALLOWED_EXTENSIONS)));
        }

        $mimeType = $this->detectMimeType($tmpName);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new LocalizedException(__('Unsupported image MIME type: %1', $mimeType ?: 'unknown'));
        }

        $imageInfo = @getimagesize($tmpName);
        if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw new LocalizedException(__('The uploaded file is not a valid image.'));
        }

        $targetDir = $this->normalizeDirectory(self::BASE_MEDIA_PATH . '/' . trim($subDirectory, '/'));
        $this->mediaDirectory->create($targetDir);

        $uploader = $this->uploaderFactory->create(['fileId' => $fieldName]);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        $uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);

        $result = $uploader->save($this->mediaDirectory->getAbsolutePath($targetDir));
        if (!is_array($result) || empty($result['file'])) {
            throw new LocalizedException(__('Unable to save uploaded image.'));
        }

        $savedFile = ltrim((string)$result['file'], '/');
        $relativePath = $targetDir . '/' . $savedFile;

        if ($oldValue && $oldValue !== $relativePath) {
            $this->deleteIfManaged($oldValue);
        }

        return $relativePath;
    }

    public function deleteIfManaged(?string $value): void
    {
        $value = trim((string)$value);
        if ($value === '' || !$this->isManagedAsset($value)) {
            return;
        }

        if ($this->mediaDirectory->isExist($value)) {
            $this->mediaDirectory->delete($value);
        }
    }

    public function isManagedAsset(?string $value): bool
    {
        $value = trim((string)$value);
        if ($value === '' || str_contains($value, '..')) {
            return false;
        }

        $normalized = ltrim(str_replace('\\', '/', $value), '/');
        $base = self::BASE_MEDIA_PATH . '/';

        return str_starts_with($normalized, $base);
    }

    private function normalizeDirectory(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?: $path;
        return trim($path, '/');
    }

    private function detectMimeType(string $tmpName): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string)finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                return $mime;
            }
        }

        return '';
    }
}
