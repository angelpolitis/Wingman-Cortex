<?php
    /*/
     * Project Name:    Wingman — Cortex — Merge Strategy
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Enums namespace.
    namespace Wingman\Cortex\Enums;

    /**
     * Defines the three available merge strategies for `Configuration::merge()`.
     *
     * A strategy determines what happens when a key exists in both the incoming data and the
     * existing configuration data.
     *
     * | Strategy | Scalar conflict | Array conflict |
     * |----------|-----------------|----------------|
     * | `REPLACE` | incoming wins | `array_replace_recursive` — incoming keys win at every nesting level |
     * | `APPEND`  | incoming wins | `array_merge_recursive` — duplicates become arrays |
     * | `DEEP`    | incoming wins | Associative arrays are deep-merged (incoming wins on conflicts); indexed arrays are replaced entirely |
     *
     * Inject a strategy per instance via `Configuration::setMergeStrategy()`. The default is
     * `REPLACE`, which preserves the pre-existing `array_replace_recursive` behaviour.
     *
     * @package Wingman\Cortex\Enums
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    enum MergeStrategy {
        /**
         * Deep-merge two arrays. Associative branch conflicts are resolved by recursion (incoming
         * wins on leaf conflicts). Indexed (list) arrays are replaced entirely by the incoming
         * array rather than appended to, so the incoming list is always the authoritative one.
         * @param array $existing The existing array.
         * @param array $incoming The incoming array to merge over the existing one.
         * @return array The merged result.
         */
        private static function applyDeep (array $existing, array $incoming) : array {
            $result = $existing;

            foreach ($incoming as $key => $value) {
                if (
                    is_string($key) &&
                    isset($result[$key]) &&
                    is_array($result[$key]) &&
                    is_array($value) &&
                    !array_is_list($value)
                ) {
                    $result[$key] = static::applyDeep($result[$key], $value);
                }
                else $result[$key] = $value;
            }

            return $result;
        }

        /**
         * Appends: incoming values are merged via `array_merge_recursive`. Array conflicts produce
         * a combined array whose duplicate scalar values become numerically-indexed arrays.
         */
        case APPEND;

        /**
         * Deep: associative array branches are merged recursively with incoming winning on
         * scalar conflicts; indexed (list) arrays are replaced entirely by the incoming value.
         */
        case DEEP;

        /**
         * Replace: incoming values always overwrite existing ones via `array_replace_recursive`.
         * This is the default strategy and preserves backwards-compatible Cortex behaviour.
         */
        case REPLACE;

        /**
         * Applies this strategy to merge `$incoming` over `$existing`.
         *
         * @param array $existing The base array (current configuration data).
         * @param array $incoming The array to merge on top.
         * @return array The merged result.
         */
        public function apply (array $existing, array $incoming) : array {
            return match ($this) {
                self::APPEND  => array_merge_recursive($existing, $incoming),
                self::DEEP    => static::applyDeep($existing, $incoming),
                self::REPLACE => array_replace_recursive($existing, $incoming),
            };
        }
    }
?>