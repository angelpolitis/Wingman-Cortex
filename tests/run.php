<?php
    /*/
     * Project Name:    Wingman — Cortex — Test Runner
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2025
     * Last Modified:   Mar 14 2025
    /*/

    use Wingman\Argus\Tester;

    require_once __DIR__ . "/../autoload.php";

    if (!class_exists(Tester::class)) {
        http_response_code(500);
        echo "Argus test framework not found. Install wingman/argus alongside wingman/cortex.";
        exit(1);
    }

    Tester::runTestsInDirectory(__DIR__, "Wingman\\Cortex\\Tests");
?>