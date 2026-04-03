<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Api;

use Magento\Framework\Exception\NoSuchEntityException;
use Pynarae\TiktokLandingPages\Model\LandingPage;

interface LandingPageRepositoryInterface
{
    public function save(LandingPage $landingPage): LandingPage;

    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $id): LandingPage;

    /**
     * @throws NoSuchEntityException
     */
    public function getByIdentifier(string $identifier, int $websiteId, bool $activeOnly = false): LandingPage;

    public function delete(LandingPage $landingPage): bool;
    public function deleteById(int $id): bool;
}
