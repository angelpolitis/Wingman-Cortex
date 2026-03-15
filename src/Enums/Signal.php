<?php
    /*/
     * Project Name:    Wingman — Cortex — Signal
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2025
     * Last Modified:   Mar 14 2025
    /*/

    # Use the Cortex.Enums namespace.
    namespace Wingman\Cortex\Enums;

    /**
     * Represents a signal emitted by Cortex during configuration lifecycle operations.
     *
     * Each case maps to a camelCase dot-notation string identifier consumed by Corvus listeners.
     * Cases can be passed directly to `emit()` — coercion to their string value via `->value` is
     * required when the method expects a plain string.
     *
     * @package Wingman\Cortex\Enums
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    enum Signal : string {
        /**
         * Emitted after a key has been successfully written via `set()` or `mergeFlat()`.
         * Payload: `fullyQualifiedKey` (string), `namespace` (string), `old` (mixed), `new` (mixed),
         * `config` (Configuration).
         */
        case CHANGED = "cortex.changed";

        /**
         * Emitted once at the end of a `batch()` call, carrying the full list of accumulated
         * change records instead of one signal per write.
         * Payload: `changes` (array of change records), `config` (Configuration).
         */
        case BATCH_CHANGED = "cortex.batchChanged";

        /**
         * Emitted in permissive mode when `merge()` silently drops an incoming key because it
         * would overwrite a constant.
         * Payload: `key` (string), `value` (mixed).
         */
        case CONSTANT_MERGE_SKIPPED = "cortex.constantMergeSkipped";
    }
?>