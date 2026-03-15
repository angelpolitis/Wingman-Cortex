<?php
    /*/
     * Project Name:    Wingman — Cortex — Corvus Bridge Emitter
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Bridge.Corvus namespace.
    namespace Wingman\Cortex\Bridge\Corvus;

    # Guard against double-inclusion (e.g. via symlinked paths resolving to different strings
    # under require_once). If the class is already in place there is nothing to do.
    if (class_exists(__NAMESPACE__ . '\Emitter', false)) return;

    # Import the following classes to the current scope.
    use BackedEnum;

    # If Corvus is installed, extend the real Emitter so callers get the full Corvus API;
    # otherwise provide a null-object stub that absorbs all calls silently.
    if (class_exists(\Wingman\Corvus\Emitter::class)) {
        /**
         * A thin extension of the Corvus `Emitter` used by `Configuration` to fire `cortex.changed`
         * signals on the active Corvus bus. Defined only when the `Wingman/Corvus` package is present.
         * @package Wingman\Cortex\Bridge\Corvus
         * @author Angel Politis <info@angelpolitis.com>
         * @since 1.0
         */
        class Emitter extends \Wingman\Corvus\Emitter {}
    }
    else {
        /**
         * A null-object stub that replaces the Corvus `Emitter` when `Wingman/Corvus` is not
         * installed. Every method returns `$this` and no signals are ever fired.
         * @package Wingman\Cortex\Bridge\Corvus
         * @author Angel Politis <info@angelpolitis.com>
         * @since 1.0
         */
        class Emitter {
            /**
             * The accumulated payload data; present only to mirror the real Emitter's interface.
             * @var array
             */
            private array $payload = [];

            /**
             * Prevent direct instantiation; use `create()` instead.
             */
            private function __construct () {}

            /**
             * Creates a new stub emitter.
             * @return static A new instance.
             */
            public static function create () : static {
                return new static();
            }

            /**
             * No-op: absorbs bus assignment calls.
             * @param string $bus The bus name.
             * @return static The emitter.
             */
            public function useBus (string $bus) : static {
                return $this;
            }

            /**
             * Accumulates payload data to mirror the real Emitter's interface.
             * @param mixed ...$data The data to accumulate.
             * @return static The emitter.
             */
            public function with (mixed ...$data) : static {
                array_push($this->payload, ...array_values($data));
                return $this;
            }

            /**
             * Replaces the current payload with the provided data.
             * @param mixed ...$data The replacement payload data.
             * @return static The emitter.
             */
            public function withOnly (mixed ...$data) : static {
                $this->payload = [];
                return $this->with(...$data);
            }

            /**
             * No-op: absorbs signal emission calls.
             * @param array|string|BackedEnum ...$signalPatterns The signal patterns.
             * @return static The emitter.
             */
            public function emit (array|string|BackedEnum ...$signalPatterns) : static {
                return $this;
            }
        }
    }
?>