<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Pynarae\TiktokLandingPages\Api\LandingPageRepositoryInterface;
use Pynarae\TiktokLandingPages\Model\ResourceModel\LandingPage as ResourceModel;
use Pynarae\TiktokLandingPages\Model\ResourceModel\LandingPage\CollectionFactory;

class LandingPageRepository implements LandingPageRepositoryInterface
{
    public function __construct(
        private readonly ResourceModel $resource,
        private readonly LandingPageFactory $landingPageFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function save(LandingPage $landingPage): LandingPage
    {
        try {
            $this->resource->save($landingPage);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Could not save landing page: %1', $e->getMessage()), $e);
        }
        return $landingPage;
    }

    public function getById(int $id): LandingPage
    {
        $model = $this->landingPageFactory->create();
        $this->resource->load($model, $id);
        if (!$model->getId()) {
            throw new NoSuchEntityException(__('Landing page with ID %1 does not exist.', $id));
        }
        return $model;
    }

    public function getByIdentifier(string $identifier, int $websiteId, bool $activeOnly = false): LandingPage
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('identifier', $identifier)
            ->addFieldToFilter('website_id', $websiteId)
            ->setPageSize(1);
        if ($activeOnly) {
            $collection->addFieldToFilter('is_active', 1);
        }
        $model = $collection->getFirstItem();
        if (!$model->getId()) {
            throw new NoSuchEntityException(__('Landing page "%1" does not exist.', $identifier));
        }
        return $model;
    }

    public function delete(LandingPage $landingPage): bool
    {
        try {
            $this->resource->delete($landingPage);
        } catch (\Throwable $e) {
            throw new CouldNotDeleteException(__('Could not delete landing page: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }
}
