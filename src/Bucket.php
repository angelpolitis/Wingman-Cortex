<?php
    /*/
     * Project Name:    Wingman — Cortex — Bucket
     * Created by:      Angel Politis
     * Creation Date:   Feb 14 2026
     * Last Modified:   Feb 16 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    /**
     * Represents a bucket.
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Bucket extends Registry {
        /**
         * The owner configuration of the bucket.
         * @var Configuration
         */
        protected Configuration $owner;

        /**
         * Constructs a new bucket with the given name and data.
         * @param string $name The name of the bucket.
         * @param Configuration $owner The owner configuration of the bucket.
         * @param array $data The data contained in the bucket.
         */
        public function __construct (string $name, Configuration $owner, array $data = []) {
            parent::__construct($name, $data, [
                "namespaceDelimiter" => $owner->getNamespaceDelimiter(),
                "segmentDelimiter" => $owner->getSegmentDelimiter()
            ]);
            $this->owner = $owner;
        }

        /**
         * Exports the bucket data, respecting the Configuration prefix.
         * @param bool $flat Whether to return a flattened version.
         * @return array
         */
        public function export (bool $flat = false, bool $namespaced = false) : array {
            if (!$flat) {
                return $this->data;
            }

            $namespaceDelimiter = $this->owner->getNamespaceDelimiter();
            $segmentDelimiter = $this->owner->getSegmentDelimiter();
            $prefix = $this->owner->getPrefix();

            if ($namespaced) {
                return static::flatten(
                    $this->data,
                    true,
                    $this->name . $namespaceDelimiter . ($prefix !== "" ? $prefix : ""),
                    $segmentDelimiter,
                    $namespaceDelimiter
                );
            }

            return static::flatten(
                $this->data,
                true,
                $prefix !== "" ? $prefix : "",
                $segmentDelimiter,
                $namespaceDelimiter
            );
        }

        /**
         * Gets the prefix of the bucket, which is the same as the owner's prefix.
         * @return string The prefix of the bucket.
         */
        public function getPrefix () : string {
            return $this->owner->getPrefix();
        }
    }
?>