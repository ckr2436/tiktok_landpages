# Pynarae_TiktokLandingPages

Magento 2 module for managing multiple TikTok ad landing pages.

## Features

- Multiple landing pages managed in Magento admin
- One public URL pattern: `/lp/{identifier}`
- TikTok WebView: jump from `<head>` as early as possible
- Non-TikTok browsers: render landing page, countdown, auto-jump, manual CTA
- PC fallback to TikTok PDP web URL
- Reads official TikTok Magento module website-level pixel/config state
- Uses official website-level pixel code by default, with optional page override
- Shows ad-ready frontend URL in admin list and edit pages

## Install

```bash
cp -R app/code/Pynarae/TiktokLandingPages /path/to/magento/app/code/Pynarae/TiktokLandingPages
bin/magento module:enable Pynarae_TiktokLandingPages
bin/magento setup:upgrade
bin/magento cache:flush
```

## Notes

- Requires the official TikTok Magento module (`Tiktok_Tiktok`) to be installed and configured.
- Landing pages are website-scoped, matching the official TikTok module's website-level configuration model.
