<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\Frontend;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Escaper;
use Magento\Framework\Module\Dir\Reader;
use Pynarae\TiktokLandingPages\Model\LandingPage;

class HtmlRenderer
{
    public function __construct(
        private readonly Reader $moduleDirReader,
        private readonly ConfigBuilder $configBuilder,
        private readonly EnvironmentDetector $environmentDetector,
        private readonly Escaper $escaper
    ) {
    }

    public function render(LandingPage $page, Http $request): string
    {
        $env = $this->environmentDetector->detect($request);
        $config = $this->configBuilder->build($page, $env);
        $configJson = $this->configBuilder->encodeForScript($config);
        $template = $this->moduleDirReader->getModuleDir('', 'Pynarae_TiktokLandingPages') . '/view/frontend/templates/render/page.php';
        $escaper = $this->escaper;

        ob_start();
        include $template;
        return (string)ob_get_clean();
    }
}
