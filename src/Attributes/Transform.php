<?php
    /*/
     * Project Name:    Wingman — Cortex — Transform
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Attributes namespace.
    namespace Wingman\Cortex\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Declares a post-read transformation to apply to a property's resolved value after primitive
     * coercion during `ObjectHydrator` hydration. Multiple `#[Transform]` attributes may be stacked
     * on a single property; they are applied in declaration order.
     *
     * The `$transformer` must be a callable-compatible string:
     * - A bare function name: `"strtolower"`.
     * - A static method reference: `"App\Types\Dsn::from"`.
     * - Any other string recognised by PHP's `is_callable()` (e.g. `"ClassName::method"`).
     *
     * Closures cannot be used here because PHP attribute values must be constant expressions.
     * For complex transformations, define a static factory method on a dedicated value-object class.
     *
     * Example:
     * ```php
     * class AppConfig {
     *     #[Configurable("app.base_url", schema: "string")]
     *     #[Transform("App\Types\Dsn::from")]
     *     protected Dsn $baseUrl;
     *
     *     #[Configurable("app.flags", schema: "string")]
     *     #[Transform("strtolower")]
     *     #[Transform("trim")]
     *     protected string $flags;
     * }
     * ```
     * @package Wingman\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
    class Transform {
        /**
         * A callable-compatible string that will be invoked with the resolved value as its sole argument.
         * @var string
         */
        public readonly string $transformer;

        /**
         * Creates a new attribute.
         * @param string $transformer A callable-compatible string (e.g. `"strtolower"`, `"Dsn::from"`).
         */
        public function __construct (string $transformer) {
            $this->transformer = $transformer;
        }
    }
?>