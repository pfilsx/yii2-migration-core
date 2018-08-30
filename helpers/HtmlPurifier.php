<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\helpers;

/**
 * HtmlPurifier provides an ability to clean up HTML from any harmful code.
 *
 * Basic usage is the following:
 *
 * ```php
 * echo HtmlPurifier::process($html);
 * ```
 *
 * If you want to configure it:
 *
 * ```php
 * echo HtmlPurifier::process($html, [
 *     'Attr.EnableID' => true,
 * ]);
 * ```
 *
 * For more details please refer to [HTMLPurifier documentation](http://htmlpurifier.org/).
 *
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @since 2.0
 */
class HtmlPurifier
{
    /**
     * Passes markup through HTMLPurifier making it safe to output to end user.
     *
     * @param string $content The HTML content to purify
     * @param array|\Closure|null $config The config to use for HtmlPurifier.
     * If not specified or `null` the default config will be used.
     * You can use an array or an anonymous function to provide configuration options:
     *
     * - An array will be passed to the `HTMLPurifier_Config::create()` method.
     * - An anonymous function will be called after the config was created.
     *   The signature should be: `function($config)` where `$config` will be an
     *   instance of `HTMLPurifier_Config`.
     *
     *   Here is a usage example of such a function:
     *
     *   ```php
     *   // Allow the HTML5 data attribute `data-type` on `img` elements.
     *   $content = HtmlPurifier::process($content, function ($config) {
     *     $config->getHTMLDefinition(true)
     *            ->addAttribute('img', 'data-type', 'Text');
     *   });
     * ```
     *
     * @return string the purified HTML content.
     */
    public static function process($content, $config = null)
    {
        $configInstance = \HTMLPurifier_Config::create($config instanceof \Closure ? null : $config);
        $configInstance->autoFinalize = false;
        $purifier = \HTMLPurifier::instance($configInstance);
        $purifier->config->set('Cache.SerializerPath', \Yii::$app->getRuntimePath());
        $purifier->config->set('Cache.SerializerPermissions', 0775);

        static::configure($configInstance);
        if ($config instanceof \Closure) {
            call_user_func($config, $configInstance);
        }

        return $purifier->purify($content);
    }

    /**
     * Allow the extended HtmlPurifier class to set some default config options.
     * @param \HTMLPurifier_Config $config
     * @since 2.0.3
     */
    protected static function configure($config)
    {
    }
}
