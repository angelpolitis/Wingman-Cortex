<?php
    /*/
     * Project Name:    Wingman — Cortex — Change Dispatcher
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 13 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Bridge\Corvus\Emitter as CorvusEmitter;
    use Wingman\Cortex\Enums\Signal;
    use Wingman\Cortex\Exceptions\MissingDependencyException;

    /**
     * Owns the change-notification pipeline for a `Configuration` instance.
     *
     * Responsibilities:
     * - Maintains the local observer registry (pattern → callable[]) used by `onChange()`.
     * - Holds the `CorvusEmitter` instance and emits `Signal::CHANGED` signals on every write.
     * - Normalises `Scope` objects to `null` before handing values to callers so that internal
     *   Cortex types never leak through the public observer interface.
     *
     * The companion `Configuration` class keeps this as a private `$dispatcher` field and exposes
     * thin proxy methods (`onChange`, `setEmitter`, `fireChange`) so the public API is unchanged.
     *
     * @package Wingman\Cortex
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    class ChangeDispatcher {
        /**
         * The Corvus signal emitter. Always present — a real `Wingman\Corvus\Emitter` subclass
         * when Corvus is installed; a null-object stub otherwise.
         * @var CorvusEmitter
         */
        private CorvusEmitter $emitter;

        /**
         * Whether the dispatcher is currently in batch mode. When `true`, `fire()` queues changes
         * into `$pendingChanges` instead of dispatching them immediately.
         * @var bool
         */
        private bool $batching = false;

        /**
         * The auto-incrementing counter used to generate unique observer handles. Starts at `0`
         * and advances by one each time `register()` is called.
         * @var int
         */
        private int $nextObserverId = 0;

        /**
         * The local change-observer registry, keyed by the opaque integer handle returned by
         * `register()`. Each entry is an array with a `"pattern"` (fnmatch-compatible glob string)
         * and a `"listener"` (callable).
         * @var array<int, array{pattern: string, listener: callable}>
         */
        private array $observers = [];

        /**
         * The queue of change records accumulated while `$batching` is `true`. Each entry is a
         * map with keys `"fqk"`, `"key"`, `"namespace"`, `"old"`, and `"new"`. Flushed and
         * cleared by `endBatch()`.
         * @var array<int, array{fqk: string, key: string, namespace: string, old: mixed, new: mixed}>
         */
        private array $pendingChanges = [];

        /**
         * Creates a new change dispatcher with the given emitter.
         * @param CorvusEmitter $emitter The Corvus emitter to emit signals on.
         */
        public function __construct (CorvusEmitter $emitter) {
            $this->emitter = $emitter;
        }

        /**
         * Dispatches a change notification for a single key write. Fires all local observer
         * callbacks whose `fnmatch()` pattern matches `$fullyQualifiedKey`, then emits a
         * `"cortex.changed"` signal on the Corvus bus (a no-op when Corvus is not installed).
         *
         * Payload passed to each observer: `($key, $namespace, $normalisedOld, $normalisedNew, $config)`.
         * `Scope` objects are normalised to `null` before dispatch so observers never receive
         * internal Cortex types.
         *
         * @param string        $fullyQualifiedKey The pre-computed `"{namespace}{delimiter}{key}"` string.
         * @param string        $key               The bare key name (without namespace prefix).
         * @param string        $namespace         The namespace the key belongs to.
         * @param mixed         $old               The previous value, or `null` if the key was absent.
         * @param mixed         $new               The incoming value.
         * @param Configuration $config            The owning configuration, forwarded to observers.
         */
        public function fire (
            string $fullyQualifiedKey,
            string $key,
            string $namespace,
            mixed $old,
            mixed $new,
            Configuration $config
        ) : void {
            $normalisedOld = $old instanceof Scope ? null : $old;
            $normalisedNew = $new instanceof Scope ? null : $new;

            if ($this->batching) {
                $this->pendingChanges[] = [
                    "fqk"       => $fullyQualifiedKey,
                    "key"       => $key,
                    "namespace" => $namespace,
                    "old"       => $normalisedOld,
                    "new"       => $normalisedNew,
                ];
                return;
            }

            foreach ($this->observers as ["pattern" => $pattern, "listener" => $listener]) {
                if (fnmatch($pattern, $fullyQualifiedKey)) {
                    $listener($key, $namespace, $normalisedOld, $normalisedNew, $config);
                }
            }

            $this->emitter
                ->withOnly($fullyQualifiedKey, $namespace, $normalisedOld, $normalisedNew, $config)
                ->emit(Signal::CHANGED);
        }

        /**
         * Returns the owned Corvus emitter.
         * @return CorvusEmitter The emitter.
         */
        public function getEmitter () : CorvusEmitter {
            return $this->emitter;
        }

        /**
         * Puts the dispatcher into batch mode. All subsequent `fire()` calls queue their change
         * records into `$pendingChanges` instead of dispatching immediately. Call `endBatch()` to
         * flush the queue. Calling `beginBatch()` when already batching resets the pending queue.
         */
        public function beginBatch () : void {
            $this->batching       = true;
            $this->pendingChanges = [];
        }

        /**
         * Ends batch mode and flushes the accumulated change records. If any changes were queued,
         * the local observers are fired once per changed key in queue order, then a single
         * `Signal::BATCH_CHANGED` signal is emitted on the Corvus bus with the full change list
         * and the owning configuration as payload. Does nothing if no changes were queued.
         * @param Configuration $config The owning configuration, forwarded to observers and the Corvus signal.
         */
        public function endBatch (Configuration $config) : void {
            $this->batching = false;
            $changes        = $this->pendingChanges;
            $this->pendingChanges = [];

            if (empty($changes)) {
                return;
            }

            foreach ($changes as ["fqk" => $fqk, "key" => $key, "namespace" => $ns, "old" => $old, "new" => $new]) {
                foreach ($this->observers as ["pattern" => $pattern, "listener" => $listener]) {
                    if (fnmatch($pattern, $fqk)) {
                        $listener($key, $ns, $old, $new, $config);
                    }
                }
            }

            $this->emitter
                ->withOnly($changes, $config)
                ->emit(Signal::BATCH_CHANGED);
        }

        /**
         * Registers a callback to be invoked whenever a key matching `$pattern` is written.
         * The pattern is matched against the fully-qualified key using `fnmatch()`, so standard
         * glob wildcards (`*`, `?`, `[seq]`) are supported.
         *
         * Returns an opaque integer handle that uniquely identifies this registration. Pass the
         * handle to `unregister()` to remove the observer.
         *
         * @param string   $pattern  A glob-style pattern to match against fully-qualified keys.
         * @param callable $listener Callback with signature
         *        `fn(string $key, string $namespace, mixed $old, mixed $new, Configuration $config): void`.
         * @return int An opaque handle for use with `unregister()`.
         */
        public function register (string $pattern, callable $listener) : int {
            $id = $this->nextObserverId++;
            $this->observers[$id] = ["pattern" => $pattern, "listener" => $listener];
            return $id;
        }

        /**
         * Removes the observer identified by the given handle. If the handle does not correspond
         * to a registered observer (e.g. it was already removed), the call is a no-op.
         * @param int $id The handle returned by `register()`.
         */
        public function unregister (int $id) : void {
            unset($this->observers[$id]);
        }

        /**
         * Replaces the owned Corvus emitter with the provided instance. Useful when the caller
         * needs to scope emissions to a specific bus or share one emitter across configurations.
         * Throws when the `Wingman\Corvus` package is not installed, because injecting a stub
         * in that context would silently swallow signal expectations.
         * @param CorvusEmitter $emitter The emitter to use.
         * @throws MissingDependencyException If the `Wingman\Corvus` package is not installed.
         */
        public function setEmitter (CorvusEmitter $emitter) : void {
            if (!class_exists(\Wingman\Corvus\Emitter::class)) {
                throw new MissingDependencyException("Wingman/Corvus");
            }

            $this->emitter = $emitter;
        }
    }
?>