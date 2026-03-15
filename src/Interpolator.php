<?php
    /*/
     * Project Name:    Wingman — Cortex — Interpolator
     * Created by:      Angel Politis
     * Creation Date:   Aug 10 2023
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use Throwable;
    use Wingman\Cortex\Exceptions\CircularReferenceException;
    use Wingman\Cortex\Interfaces\InterpolationContext;
    use Wingman\Cortex\Variable;

    /**
     * A class that provides configuration loading capabilities for Wingman components.
     *
     * After all `@{...}` variable tokens have been replaced in a string, the result may itself be a
     * computable expression — for example `3.99 * 1.2` or `round(3.99 * 1.2, 2)`. The `evaluate()`
     * method handles such cases, but only evaluates the expression if it is proven safe by a strict
     * token-level whitelist check. Any token that is not a numeric or string literal, an operator, or a
     * function from the explicit safe-functions list causes a silent bail-out, returning the string
     * unchanged. This prevents remote code execution via crafted configuration values.
     *
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Interpolator {
        /**
         * The regular expression used to match environmental variables inside strings.  
         *   
         * The expression is analysed as follows:  
         * • `expression = @{environment::namespace:variable}`  
         * • `environment = letterOrNumber+([hyphen|space+]letterOrNumber+)?`
         * • `namespace = letterOrNumber+([hyphen|space+]letterOrNumber+)?`  
         * • `variable = letterOrNumber+([hyphen|space+|dot]letterOrNumber+)?`
         * @var string
         */
        public const string VARIABLE_PATTERN = '@{((?:[^\/\n{}]+(?<!\\\\)::|(?<!\\\\)#[^\/\n{}]+(?<!\\\\)\/|[^\/!\n{}]+\/)?(?:[^\n{}]+(?<!\\\\):|(?<!\\\\)![^\n{}]+(?<!\\\\)\/|[^\n{}]+\/)?[^\/\n{}]+)}';

        /**
         * The optional interpolation context that overrides the built-in token pattern and provides
         * a secondary match-validity predicate. When `null`, `Interpolator` falls back to the
         * built-in `VARIABLE_PATTERN` and accepts every match unconditionally.
         * @var InterpolationContext|null
         */
        private ?InterpolationContext $context = null;

        /**
         * The explicit set of PHP functions that may appear in a post-interpolation expression passed
         * to `evaluate()`. Every other function name causes the expression to be returned as a plain
         * string without evaluation.
         * @var string[]
         */
        private const SAFE_FUNCTIONS = [
            "abs", "ceil", "floor", "round", "max", "min", "pow", "sqrt", "fmod", "intdiv",
            "strlen", "mb_strlen", "strtolower", "strtoupper", "mb_strtolower", "mb_strtoupper",
            "ucfirst", "lcfirst", "ucwords", "trim", "ltrim", "rtrim", "str_pad", "str_repeat",
            "substr", "str_replace", "number_format", "sprintf", "implode", "explode",
            "count", "intval", "floatval", "strval"
        ];

        /**
         * The single-character tokens that are permitted to appear in a safe expression. This covers
         * all arithmetic operators, grouping, comma (for function arguments), and the string
         * concatenation operator.
         * @var string[]
         */
        private const SAFE_CHARS = ['(', ')', ',', '+', '-', '*', '/', '%', '.', ';'];

        /**
         * The stack of variable being currently resolved.
         * @var string[]
         */
        protected array $stack = [];

        /**
         * Evaluates a post-interpolation expression if it contains function calls or arithmetic
         * operations, and every token it contains is either a safe literal, an arithmetic operator,
         * or a function from the explicit whitelist. Any expression with an unrecognised token is
         * returned as a plain string without evaluation, preventing code injection.
         * @param mixed $value The value to evaluate.
         * @return mixed The evaluated result, or the original string if evaluation is not safe or applicable.
         */
        protected function evaluate (mixed $value) : mixed {
            if (!is_string($value)) return $value;

            $value = preg_replace("/^'(.*)'$|^\"(.*)\"$/s", "$1$2", $value);

            if (!str_contains($value, '(') || !str_contains($value, ')')) {
                return $value;
            }

            $tokens = @token_get_all("<?php ({$value});");

            if (!is_array($tokens)) return $value;

            foreach ($tokens as $token) {
                if (is_array($token)) {
                    [$id] = $token;

                    if (in_array($id, [T_OPEN_TAG, T_WHITESPACE, T_LNUMBER, T_DNUMBER, T_CONSTANT_ENCAPSED_STRING], true)) {
                        continue;
                    }

                    if ($id === T_STRING && in_array(strtolower($token[1]), self::SAFE_FUNCTIONS, true)) {
                        continue;
                    }

                    return $value;
                }

                if (!in_array($token, self::SAFE_CHARS, true)) return $value;
            }

            try {
                return eval("return ({$value});");
            }
            catch (Throwable) {
                return $value;
            }
        }
        
        /**
         * Interpolates all string values within an iterable recursively.
         * @param iterable $value The iterable to interpolate.
         * @param callable $resolver A callback function that takes a raw expression and returns its resolved value.
         * @param array $namespaces The current namespaces for resolving variables.
         * @param array $environments The current environments for resolving variables.
         * @return iterable The interpolated iterable.
         */
        protected function interpolateIterable (iterable $value, callable $resolver, array $namespaces, array $environments) : iterable {
            $isObj = is_object($value);
            $target = $isObj ? clone $value : [];

            foreach ($value as $key => $val) {
                $newKey = $this->interpolate($key, $resolver, $namespaces, $environments);
                $newVal = $this->interpolate($val, $resolver, $namespaces, $environments);

                if ($isObj) {
                    $target->{$newKey} = $newVal;
                    if ($key !== $newKey) unset($target->{$key});
                }
                else $target[$newKey] = $newVal;
            }
            return $target;
        }

        /**
         * Removes values from the resolving stack.
         */
        protected function pop () : void {
            array_pop($this->stack);
        }

        /**
         * Injects an `InterpolationContext` to override the default token pattern and gain a
         * secondary match-validity predicate. Pass `null` to revert to the built-in behaviour.
         * @param InterpolationContext|null $context The context to use, or `null` to unset.
         * @return static The interpolator.
         */
        public function setContext (?InterpolationContext $context) : static {
            $this->context = $context;
            return $this;
        }

        /**
         * Resolves a string by replacing all variable patterns with their resolved values.
         * @param string $value The string to resolve.
         * @param callable $resolver A callback function that takes a raw expression and returns its resolved value.
         * @param array $namespaces The current namespaces for resolving variables.
         * @param array $environments The current environments for resolving variables.
         * @return mixed The resolved value, or the original string if it doesn't contain any variable patterns.
         */
        protected function resolve (string $value, callable $resolver, array $namespaces, array $environments) : mixed {
            $pattern = $this->context !== null
                ? sprintf("/%s/", $this->context->getPattern())
                : sprintf("/%s/", static::VARIABLE_PATTERN);
            
            while (preg_match_all($pattern, $value, $groups, PREG_OFFSET_CAPTURE)) {
                $unreplaced = 0;

                foreach ($groups[1] as $index => [$rawExpression, $charOffset]) {
                    $token = $groups[0][$index][0];

                    if ($this->context !== null && !$this->context->isValid($rawExpression, $charOffset, $value)) {
                        $unreplaced++;
                        continue;
                    }

                    $this->track($rawExpression);
                    
                    try {
                        $resolved = $resolver(Variable::from($rawExpression), $namespaces, $environments);
                    }
                    finally {
                        $this->pop();
                    }

                    if ($resolved === null) {
                        $unreplaced++;
                        continue;
                    }

                    if ($value === $token) return $resolved;

                    $replacement = match (true) {
                        is_bool($resolved) => var_export($resolved, true),
                        is_null($resolved) => "null",
                        is_iterable($resolved) => json_encode($resolved),
                        default => preg_replace("/^'(.*)'$|^\"(.*)\"$/", "$1$2", (string) $resolved)
                    };
                    $value = str_replace($token, $replacement, $value);
                }

                if ($unreplaced === count($groups[1])) break;
            }

            return $this->evaluate($value);
        }

        /**
         * Tracks a variable being resolved to detect circular references.
         * @param string $value The variable being resolved.
         * @throws CircularReferenceException If a circular reference is detected.
         */
        protected function track (string $value) : void {
            if (in_array($value, $this->stack, true)) {
                throw new CircularReferenceException([...$this->stack, $value]);
            }
            $this->stack[] = $value;
        }

        /**
         * Interpolates a value by resolving all variable patterns within it.
         * @param mixed $value The value to interpolate. If it's an iterable, all string values within it will be interpolated recursively.
         * @param callable $resolver A callback function that takes a raw expression and returns its resolved value.
         * @param array $namespaces The current namespaces for resolving variables.
         * @param array $environments The current environments for resolving variables.
         * @return mixed The interpolated value.
         */
        public function interpolate (mixed $value, callable $resolver, array $namespaces = [], array $environments = []) : mixed {
            if (is_iterable($value)) {
                return $this->interpolateIterable($value, $resolver, $namespaces, $environments);
            }

            if (!is_string($value)) {
                return $value;
            }

            return $this->resolve($value, $resolver, $namespaces, $environments);
        }
    }
?>