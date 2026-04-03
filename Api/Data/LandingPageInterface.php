<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Api\Data;

interface LandingPageInterface
{
    public const ENTITY_ID = 'entity_id';
    public const WEBSITE_ID = 'website_id';
    public const TITLE = 'title';
    public const IDENTIFIER = 'identifier';
    public const IS_ACTIVE = 'is_active';
    public const TEMPLATE_CODE = 'template_code';
    public const RAW_PDP_URL = 'raw_pdp_url';
    public const PRODUCT_ID = 'product_id';
    public const SOURCE = 'source';
    public const PC_FALLBACK_URL = 'pc_fallback_url';
    public const MOBILE_FALLBACK_URL = 'mobile_fallback_url';
    public const PIXEL_CODE_OVERRIDE = 'pixel_code_override';
    public const CONTENT_NAME = 'content_name';
    public const PROMO_BAR_TEXT = 'promo_bar_text';
    public const HEADLINE = 'headline';
    public const SUBHEADLINE = 'subheadline';
    public const CTA_TEXT = 'cta_text';
    public const DESKTOP_MESSAGE = 'desktop_message';
    public const FAIL_MESSAGE = 'fail_message';
    public const HERO_IMAGE_URL = 'hero_image_url';
    public const CTA_BG_IMAGE_URL = 'cta_bg_image_url';
    public const PROMO_IMAGE_URL = 'promo_image_url';
    public const HEAD_JUMP_ENABLED = 'head_jump_enabled';
    public const MANUAL_BUTTON_ENABLED = 'manual_button_enabled';
    public const BODY_AUTO_JUMP_ENABLED = 'body_auto_jump_enabled';
    public const HEAD_JUMP_TIMEOUT_MS = 'head_jump_timeout_ms';
    public const AUTO_JUMP_SECONDS = 'auto_jump_seconds';
    public const PASSTHROUGH_PARAMS = 'passthrough_params';
    public const META_TITLE = 'meta_title';
    public const META_DESCRIPTION = 'meta_description';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getId();
    public function getData($key = '', $index = null);
    public function setData($key, $value = null);
}
