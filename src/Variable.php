<?php
    /*/
	 * Project Name:    Wingman — Cortex — Variable
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 08 2025
	 * Last Modified:   Feb 14 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Exceptions\InvalidKeyException;

    /**
     * Represents a variable.
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Variable {
        /**
         * The regular expression used to match bare variables.  
         *   
         * The expression is analysed as follows:  
         * • `expression = environment::namespace:variable`  
         * • `namespace = letterOrNumber+([hyphen|space+]letterOrNumber+)?`  
         * • `variable = letterOrNumber+([hyphen|space+|dot]letterOrNumber+)?`
         * @var string
         */
        public const string PATTERN = '(?:(?<e>[^\/\n!{}]+)(?<!\\\\)::|(?<!\\\\)#(?<e2>[^\/\n!{}]+)(?<!\\\\)\/|(?<e3>[^\/\n!{}]+)\/)?(?:(?<n>[^\n{}]+)(?<!\\\\):|(?<!\\\\)!(?<n2>[^\n{}]+)(?<!\\\\)\/|(?<n3>[^\n{}]+)\/)?(?<v>[^\/\n{}]+)';

        /**
         * Sentinel value used by {@see with()} to distinguish "keep the current value" from an explicit `null`.
         * A null-byte character is used because it can never appear in a valid variable identifier.
         * @var string
         */
        private const UNCHANGED = "\x00";
        /**
         * Creates a new variable.
         * @param string $name The name of the variable.
         * @param string|null $namespace The namespace of the variable.
         * @param string|null $environment The environment of the variable.
         */
        public function __construct (
            public readonly string $name,
            public readonly ?string $namespace = null,
            public readonly ?string $environment = null
        ) {}

        /**
         * Provides debug information about the variable.
         * @return array An associative array containing the variable's properties.
         */
        public function __debugInfo () : array {
            return [
                "name" => $this->name,
                "namespace" => $this->namespace,
                "environment" => $this->environment
            ];
        }

        /**
         * Serialises a variable to an array.
         * @return array An associative array containing the variable's properties.
         */
        public function __serialize () : array {
            return [
                "name" => $this->name,
                "namespace" => $this->namespace,
                "environment" => $this->environment
            ];
        }

        /**
         * Converts the variable to a string in the format `environment::namespace:name`.
         * @return string The string representation of the variable.
         */
        public function __toString () : string {
            $envPart = $this->environment ? "{$this->environment}::" : "";
            $nsPart = $this->namespace ? "{$this->namespace}:" : "";
            return "{$envPart}{$nsPart}{$this->name}";
        }

        /**
         * Unserialises a variable from an array.
         * @param array $data An associative array containing the variable's properties.
         */
        public function __unserialize (array $data) : void {
            $this->name = $data["name"];
            $this->namespace = $data["namespace"];
            $this->environment = $data["environment"];
        }

        /**
         * Checks whether a variable is equal to another variable.
         * @param Variable $other The variable to compare with.
         * @return bool Whether the variables are equal.
         */
        public function equals (Variable $other) : bool {
            return $this->getId() === $other->getId();
        }

        /**
         * Creates a variable from a string expression.
         * @param string $string The string expression to parse.
         * @return static The created variable instance.
         * @throws Exception If the string is not a valid variable expression or if the variable name is missing.
         */
        public static function from (string $string) : static {
            $result = [
                "environment" => null,
                "namespace" => null,
                "name" => null
            ];

            $map = ['e' => "environment", 'n' => "namespace", 'v' => "name"];

            $doesMatch = preg_match_all("/^" . static::PATTERN . "$/", $string, $matches);

            if (!$doesMatch) throw new InvalidKeyException($string, "not a valid variable expression");

            foreach ($matches as $key => $match) {
                if (!is_array($match) || !array_filter($match) || is_numeric($key)) continue;

                $key = $map[preg_replace("/\d$/", "", $key)] ?? null;

                if (is_null($key)) continue;

                $result[$key] = $match[0];
            }

            if (is_null($result["name"])) throw new InvalidKeyException($string, "variable name is required");
            
            return new static($result["name"], $result["namespace"], $result["environment"]);
        }

        /**
         * Gets the environment of a variable.
         * @return string|null The environment of the variable, or `null` if it doesn't have one.
         */
        public function getEnvironment () : ?string {
            return $this->environment;
        }

        /**
         * Gets a unique identifier for a variable.
         * _Two variables having the same properties are considered the same._
         * @return string A unique identifier.
         */
        public function getId () : string {
            return sprintf("%s::%s:%s", $this->environment ?? "", $this->namespace ?? "", $this->name);
        }

        /**
         * Gets the name of a variable.
         * @return string The name of the variable.
         */
        public function getName () : string {
            return $this->name;
        }

        /**
         * Gets the namespace of a variable.
         * @return string|null The namespace of the variable, or `null` if it doesn't have one.
         */
        public function getNamespace () : ?string {
            return $this->namespace;
        }

        /**
         * Creates a new variable with the same properties as the current variable, but with any specified properties overridden.
         * Omitting a parameter preserves the current value.
         * To explicitly clear `$namespace` or `$environment`, pass `null` for that parameter.
         * @param string|null $name The name for the new variable, or `null` to keep the same name.
         * @param string|null $namespace The namespace for the new variable, `null` to clear it, or omit to keep the current namespace.
         * @param string|null $environment The environment for the new variable, `null` to clear it, or omit to keep the current environment.
         * @return static A new variable instance with the specified properties.
         */
        public function with (?string $name = self::UNCHANGED, ?string $namespace = self::UNCHANGED, ?string $environment = self::UNCHANGED) : static {
            $name = ($name === null || $name === self::UNCHANGED) ? $this->name : $name;
            $namespace = ($namespace === self::UNCHANGED) ? $this->namespace : $namespace;
            $environment = ($environment === self::UNCHANGED) ? $this->environment : $environment;
            return new static($name, $namespace, $environment);
        }

        /**
         * Creates a new variable with the same name and namespace but a different environment.
         * @param string|null $environment The environment for the new variable, or `null` to remove the environment.
         * @return static A new variable instance with the specified environment.
         */
        public function withEnvironment (?string $environment) : static {
            return new static($this->name, $this->namespace, $environment);
        }

        /**
         * Creates a new variable with the same namespace and environment but a different name.
         * @param string $name The name for the new variable.
         * @return static A new variable instance with the specified name.
         */
        public function withName (string $name) : static {
            return new static($name, $this->namespace, $this->environment);
        }

        /**
         * Creates a new variable with the same name and environment but a different namespace.
         * @param string|null $namespace The namespace for the new variable, or `null` to remove the namespace.
         * @return static A new variable instance with the specified namespace.
         */
        public function withNamespace (?string $namespace) : static {
            return new static($this->name, $namespace, $this->environment);
        }
    }
?>