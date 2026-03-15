<?php
    /*/
     * Project Name:    Wingman — Cortex — NoInterpolate
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Attributes namespace.
    namespace Wingman\Cortex\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Instructs `ObjectHydrator` to bypass the `Interpolator` when resolving a property's
     * configuration value — i.e. to call `Configuration::getRaw()` instead of
     * `Configuration::get()`.
     *
     * Useful when a property intentionally stores a template string that contains Cortex
     * interpolation tokens (`@{...}`) but should not have those tokens expanded. Common cases:
     * - Log format patterns using `@{timestamp}`, `@{level}` etc.
     * - Reusable URL templates: `"https://@{host}/@{path}"`.
     * - Mail subject / body templates that will be rendered by a separate template engine.
     *
     * Example:
     * ```php
     * class MailConfig {
     *     #[Configurable("mail.subject_template", schema: "string")]
     *     #[NoInterpolate]
     *     protected string $subjectTemplate;
     * }
     * ```
     * @package Wingman\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class NoInterpolate {}
?>