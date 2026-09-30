/**
 * JavaScript module for Netformic Microsoft BC Integration.
 */
import './component/redis-cache-config';
import './module/sw-order/page/sw-order-detail';
import './module/sw-customer/page/sw-customer-detail';
import './module/sw-product/page/sw-product-detail';

import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('de-DE', deDE);
