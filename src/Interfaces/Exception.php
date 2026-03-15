<?php
    /*/
	 * Project Name:    Wingman — Cortex — Exception Interface
	 * Created by:      Angel Politis
	 * Creation Date:   Mar 11 2026
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Cortex.Interfaces namespace.
    namespace Wingman\Cortex\Interfaces;

    /**
     * Marker interface implemented by every Cortex-specific exception.
     *
     * Catch this interface to handle any exception thrown by the Cortex package
     * without needing to enumerate individual exception classes.
     *
     * @package Wingman\Cortex\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Exception {}
?>